<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Catalog;

use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\Provider\VectorMath;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Catalog;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ProductIndex;

/**
 * Top-K cosine search over stored product embeddings. Candidates are products sharing a category with the
 * query product (bounded), falling back to the whole store index for small catalogs.
 */
class VectorSearch
{
    /**
     * @param ProductIndex $productIndex
     * @param Catalog $catalog
     * @param Config $config
     */
    public function __construct(
        private readonly ProductIndex $productIndex,
        private readonly Catalog $catalog,
        private readonly Config $config
    ) {
    }

    /**
     * @param int $storeId
     * @param int $websiteId
     * @param int $productId
     * @param int $topK
     * @param int|null $maxCandidates
     * @return array<int, float> product ID => similarity, highest first
     */
    public function similarTo(int $storeId, int $websiteId, int $productId, int $topK, ?int $maxCandidates = null): array
    {
        $row = $this->productIndex->getRows($storeId, [$productId])[$productId] ?? null;
        if (!$row || $row['status'] !== ProductIndex::STATUS_INDEXED || empty($row['vector'])) {
            return [];
        }
        $query = VectorMath::unpack($row['vector']);
        $maxCandidates ??= max(50, $this->config->getInt('catalog/max_similarity_candidates', null, 3000));
        $categories = $this->catalog->getCategoryIds([$productId])[$productId] ?? [];
        $candidateIds = $categories
            ? $this->catalog->getProductsInCategories($categories, $websiteId, $maxCandidates, [$productId])
            : [];
        $vectors = $this->productIndex->getVectors($storeId, $candidateIds ?: null, (string)$row['model']);
        if (!$candidateIds && count($vectors) > $maxCandidates) {
            $vectors = array_slice($vectors, 0, $maxCandidates, true);
        }
        unset($vectors[$productId]);
        return $this->rank($query, $vectors, $topK);
    }

    /**
     * @param float[] $query
     * @param array<int, string|float[]> $vectors encoded or decoded
     * @param int $topK
     * @return array<int, float>
     */
    public function rank(array $query, array $vectors, int $topK): array
    {
        $scores = [];
        foreach ($vectors as $id => $vector) {
            $decoded = is_array($vector) ? $vector : VectorMath::unpack($vector);
            $score = VectorMath::cosine($query, $decoded);
            if ($score > 0.05) {
                $scores[(int)$id] = round($score, 6);
            }
        }
        arsort($scores);
        return array_slice($scores, 0, $topK, true);
    }
}
