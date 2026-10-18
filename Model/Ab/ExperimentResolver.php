<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Ab;

use Saurabhg\AIproductRecommend\Model\Experiment;
use Saurabhg\AIproductRecommend\Model\Recommendation\Context;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Experiment as ExperimentResource;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Experiment\CollectionFactory;
use Saurabhg\AIproductRecommend\Model\Source\ExperimentStatus;

/**
 * Consistent, persisted variant assignment of a visitor / customer to running experiments
 */
class ExperimentResolver
{
    /**
     * @var array<string, Experiment|null>
     */
    private array $experiments = [];

    /**
     * @param CollectionFactory $collectionFactory
     * @param ExperimentResource $resource
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly ExperimentResource $resource
    ) {
    }

    /**
     * Assigns the context to a variant of the running experiment of the unit (if any)
     *
     * @param Context $context
     * @param string $unitCode
     * @return void
     */
    public function resolve(Context $context, string $unitCode): void
    {
        $context->experimentId = $context->variantId = $context->variantCode = null;
        $context->variantConfig = [];
        $subject = $context->getSubjectKey();
        $experiment = $this->getRunning($unitCode, $context->storeId);
        if (!$experiment || $subject === null) {
            return;
        }
        $variants = $this->resource->getVariants((int)$experiment->getId());
        if (!$variants) {
            return;
        }
        $experimentId = (int)$experiment->getId();
        $variantId = $this->resource->getAssignment($experimentId, $subject);
        if ($variantId === null || !isset($variants[$variantId])) {
            $variantId = $this->pick($experimentId, $subject, $variants);
            if ($context->channel !== Context::CHANNEL_ADMIN) {
                // simulations never create assignments
                $variantId = $this->resource->assign($experimentId, $variantId, $subject);
            }
        }
        $variant = $variants[$variantId] ?? reset($variants);
        $config = json_decode((string)($variant['configuration'] ?? ''), true);
        $context->experimentId = $experimentId;
        $context->variantId = (int)$variant['variant_id'];
        $context->variantCode = (string)$variant['code'];
        $context->variantConfig = is_array($config) ? $config : [];
    }

    /**
     * Deterministic hash bucketing by allocation percentage
     *
     * @param int $experimentId
     * @param string $subject
     * @param array<int, array> $variants
     * @return int variant ID
     */
    public function pick(int $experimentId, string $subject, array $variants): int
    {
        $total = array_sum(array_map(static fn($v) => max(0, (int)$v['allocation']), $variants));
        if ($total <= 0) {
            return (int)array_key_first($variants);
        }
        $bucket = crc32($experimentId . ':' . $subject) % $total;
        $cumulative = 0;
        foreach ($variants as $variantId => $variant) {
            $cumulative += max(0, (int)$variant['allocation']);
            if ($bucket < $cumulative) {
                return (int)$variantId;
            }
        }
        return (int)array_key_last($variants);
    }

    /**
     * @param string $unitCode
     * @param int $storeId
     * @return Experiment|null
     */
    private function getRunning(string $unitCode, int $storeId): ?Experiment
    {
        $key = $unitCode . '|' . $storeId;
        if (!array_key_exists($key, $this->experiments)) {
            $now = gmdate('Y-m-d H:i:s');
            $this->experiments[$key] = null;
            $collection = $this->collectionFactory->create()
                ->addFieldToFilter('unit_code', $unitCode)
                ->addFieldToFilter('status', ExperimentStatus::RUNNING)
                ->addFieldToFilter('start_at', [['null' => true], ['lteq' => $now]])
                ->addFieldToFilter('end_at', [['null' => true], ['gteq' => $now]])
                ->setOrder('experiment_id', 'ASC');
            /** @var Experiment $experiment */
            foreach ($collection as $experiment) {
                if ($experiment->isForStore($storeId)) {
                    $this->experiments[$key] = $experiment;
                    break;
                }
            }
        }
        return $this->experiments[$key];
    }
}
