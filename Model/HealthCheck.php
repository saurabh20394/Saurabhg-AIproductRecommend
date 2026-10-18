<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model;

use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Driver\File;
use Saurabhg\AIproductRecommend\Api\Data\HealthComponentInterface as Health;
use Saurabhg\AIproductRecommend\Api\HealthCheckInterface;
use Saurabhg\AIproductRecommend\Model\Cache\Type as CacheType;
use Saurabhg\AIproductRecommend\Model\Catalog\IndexQueue;
use Saurabhg\AIproductRecommend\Model\Data\HealthComponentFactory;
use Saurabhg\AIproductRecommend\Model\Event\EventIngestion;
use Saurabhg\AIproductRecommend\Model\Provider\CircuitBreaker;
use Saurabhg\AIproductRecommend\Model\Recommendation\RecommendationService;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ErrorLog;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ProductIndex;

/**
 * Dependency state for the dashboard and the health endpoint (no secrets are exposed)
 */
class HealthCheck implements HealthCheckInterface
{
    private const QUEUE_STATUS_NEW = 2;
    private const QUEUE_STATUS_IN_PROGRESS = 3;
    private const QUEUE_STATUS_RETRY = 5;

    /**
     * @param Config $config
     * @param CircuitBreaker $circuitBreaker
     * @param StateInterface $cacheState
     * @param ResourceConnection $resourceConnection
     * @param ProductIndex $productIndex
     * @param ErrorLog $errorLog
     * @param StoreResolver $storeResolver
     * @param HealthComponentFactory $componentFactory
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param File $fileDriver
     */
    public function __construct(
        private readonly Config $config,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly StateInterface $cacheState,
        private readonly ResourceConnection $resourceConnection,
        private readonly ProductIndex $productIndex,
        private readonly ErrorLog $errorLog,
        private readonly StoreResolver $storeResolver,
        private readonly HealthComponentFactory $componentFactory,
        private readonly ComponentRegistrarInterface $componentRegistrar,
        private readonly File $fileDriver
    ) {
    }

    /**
     * @inheritdoc
     */
    public function check(): array
    {
        return [
            $this->module(),
            $this->aiService(),
            $this->embeddings(),
            $this->cache(),
            $this->queue(),
            $this->catalogIndex(),
            $this->jobErrors(),
        ];
    }

    /**
     * @return string
     */
    public function getModuleVersion(): string
    {
        try {
            $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, 'Saurabhg_AIproductRecommend');
            $composer = json_decode((string)$this->fileDriver->fileGetContents($path . '/composer.json'), true);
            return (string)($composer['version'] ?? 'unknown');
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }

    /**
     * @return Health
     */
    private function module(): Health
    {
        $stores = $this->storeResolver->getStoreIds();
        return $this->component(
            'Module',
            $stores ? Health::STATUS_OK : Health::STATUS_DISABLED,
            sprintf('v%s, PHP %s, enabled in %d store view(s)', $this->getModuleVersion(), PHP_VERSION, count($stores))
        );
    }

    /**
     * @return Health
     */
    private function aiService(): Health
    {
        if (!$this->config->isRemoteRankingEnabled() && $this->config->getEmbeddingProvider() === Config::PROVIDER_LOCAL) {
            return $this->component('AI Service', Health::STATUS_DISABLED, 'Local engine (no external AI service configured)');
        }
        $state = $this->circuitBreaker->getState(RecommendationService::DEPENDENCY_RANKING);
        if (($state['open_until'] ?? 0) > time()) {
            return $this->component('AI Service', Health::STATUS_DOWN, 'Circuit open: ' . ($state['last_error'] ?? 'repeated failures'));
        }
        if (($state['failures'] ?? 0) > 0) {
            return $this->component('AI Service', Health::STATUS_WARNING, sprintf('%d recent failure(s): %s', $state['failures'], $state['last_error'] ?? ''));
        }
        return $this->component('AI Service', Health::STATUS_OK, !empty($state['last_success'])
            ? 'Last success ' . gmdate('Y-m-d H:i', (int)$state['last_success']) . ' UTC'
            : 'Configured, no calls yet');
    }

    /**
     * @return Health
     */
    private function embeddings(): Health
    {
        $provider = $this->config->getEmbeddingProvider();
        return $this->component(
            'Embedding Provider',
            Health::STATUS_OK,
            $provider === Config::PROVIDER_LOCAL
                ? 'Local hashing embeddings'
                : 'HTTP: ' . ($this->config->getValue('ai/embedding_model') ?: 'default model')
        );
    }

    /**
     * @return Health
     */
    private function cache(): Health
    {
        return $this->cacheState->isEnabled(CacheType::TYPE_IDENTIFIER)
            ? $this->component('Recommendation Cache', Health::STATUS_OK, 'Cache type "AI Recommendations" enabled')
            : $this->component('Recommendation Cache', Health::STATUS_WARNING, 'Cache type disabled: every request is generated live');
    }

    /**
     * @return Health
     */
    private function queue(): Health
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $pending = (int)$connection->fetchOne(
                $connection->select()
                    ->from(['s' => $this->resourceConnection->getTableName('queue_message_status')], [new \Zend_Db_Expr('COUNT(*)')])
                    ->join(['m' => $this->resourceConnection->getTableName('queue_message')], 'm.id = s.message_id', [])
                    ->where('m.topic_name IN (?)', [EventIngestion::TOPIC, IndexQueue::TOPIC])
                    ->where('s.status IN (?)', [self::QUEUE_STATUS_NEW, self::QUEUE_STATUS_IN_PROGRESS, self::QUEUE_STATUS_RETRY])
            );
        } catch (\Throwable $e) {
            return $this->component('Queue', Health::STATUS_WARNING, 'Database queue not used (AMQP or unavailable)');
        }
        return $this->component(
            'Queue',
            $pending > 1000 ? Health::STATUS_WARNING : Health::STATUS_OK,
            sprintf('%d pending message(s)', $pending)
        );
    }

    /**
     * @return Health
     */
    private function catalogIndex(): Health
    {
        $total = $indexed = $failed = $pending = 0;
        foreach ($this->productIndex->getStatusSummary() as $counts) {
            $total += array_sum($counts);
            $indexed += ($counts[ProductIndex::STATUS_INDEXED] ?? 0) + ($counts[ProductIndex::STATUS_SKIPPED] ?? 0);
            $failed += $counts[ProductIndex::STATUS_FAILED] ?? 0;
            $pending += $counts[ProductIndex::STATUS_PENDING] ?? 0;
        }
        if (!$total) {
            return $this->component('Catalog Index', Health::STATUS_WARNING, 'Not indexed yet: run bin/magento airecommend:reindex');
        }
        $percent = round($indexed / $total * 100, 1);
        return $this->component(
            'Catalog Index',
            $failed ? Health::STATUS_WARNING : Health::STATUS_OK,
            sprintf('%s%% indexed, %d pending, %d failed', $percent, $pending, $failed)
        );
    }

    /**
     * @return Health
     */
    private function jobErrors(): Health
    {
        $open = $this->errorLog->countOpen();
        return $this->component('Job Errors', $open ? Health::STATUS_WARNING : Health::STATUS_OK, sprintf('%d open error(s)', $open));
    }

    /**
     * @param string $name
     * @param string $status
     * @param string $message
     * @return Health
     */
    private function component(string $name, string $status, string $message): Health
    {
        return $this->componentFactory->create(['data' => ['name' => $name, 'status' => $status, 'message' => $message]]);
    }
}
