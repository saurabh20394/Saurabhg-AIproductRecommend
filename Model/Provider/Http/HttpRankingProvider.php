<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Provider\Http;

use Magento\Framework\Exception\LocalizedException;
use Saurabhg\AIproductRecommend\Api\RankingProviderInterface;
use Saurabhg\AIproductRecommend\Model\Config;

/**
 * External recommendation API (FastAPI contract, requirements §21)
 */
class HttpRankingProvider implements RankingProviderInterface
{
    private const MAX_ITEMS = 200;

    /**
     * @param Client $client
     * @param Config $config
     */
    public function __construct(
        private readonly Client $client,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritdoc
     */
    public function recommend(array $request, int $storeId): array
    {
        $response = $this->client->postJson(
            $this->config->getServiceUrl('ai/ranking_path', $storeId),
            $request,
            $storeId,
            max(50, $this->config->getInt('ai/timeout_ms', $storeId, 800))
        );
        return $this->validate($response);
    }

    /**
     * Schema validation of the untrusted response
     *
     * @param array $response
     * @return array
     * @throws LocalizedException
     */
    public function validate(array $response): array
    {
        $items = $response['recommendations'] ?? null;
        if (!is_array($items)) {
            throw new LocalizedException(__('Recommendation response has no "recommendations" list.'));
        }
        $clean = [];
        foreach (array_slice($items, 0, self::MAX_ITEMS) as $item) {
            if (!is_array($item) || !isset($item['sku']) || !is_scalar($item['sku'])) {
                continue;
            }
            $sku = trim((string)$item['sku']);
            if ($sku === '' || strlen($sku) > 64) {
                continue;
            }
            $score = isset($item['score']) && is_numeric($item['score']) ? (float)$item['score'] : 0.0;
            $reason = isset($item['reason_code']) && is_string($item['reason_code'])
                ? preg_replace('/[^a-z0-9_]/', '', strtolower($item['reason_code']))
                : '';
            $clean[] = ['sku' => $sku, 'score' => max(0.0, min(1.0, $score)), 'reason_code' => (string)$reason];
        }
        $source = isset($response['source']) && is_string($response['source'])
            ? preg_replace('/[^a-z0-9_\-]/i', '', $response['source']) : 'remote';
        $model = isset($response['model_version']) && is_string($response['model_version'])
            ? preg_replace('/[^a-z0-9_.\-]/i', '', $response['model_version']) : '';
        return [
            'source' => mb_substr((string)$source, 0, 24) ?: 'remote',
            'model_version' => mb_substr((string)$model, 0, 64),
            'recommendations' => $clean,
        ];
    }
}
