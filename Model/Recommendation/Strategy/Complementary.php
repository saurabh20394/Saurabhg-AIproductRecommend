<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Recommendation\Strategy;

use Saurabhg\AIproductRecommend\Model\Recommendation\CandidateSet;
use Saurabhg\AIproductRecommend\Model\Recommendation\Context;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Relation;

/**
 * Complete the look: co-purchased products from other categories plus popular products of the
 * merchant-configured complementary categories (unit "Complementary Category Map")
 */
class Complementary extends AbstractStrategy
{
    /**
     * Complementary category map of the unit being served (set by the service)
     *
     * @var array<string, int[]>
     */
    private array $map = [];

    /**
     * @param array<string, int[]> $map
     * @return $this
     */
    public function setMap(array $map): self
    {
        $this->map = $map;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function collect(Context $context, int $limit): CandidateSet
    {
        $set = $this->newSet();
        $seeds = $this->getSeeds($context);
        if (!$seeds) {
            return $set;
        }
        $size = $this->fetchSize($limit);
        $seedCategories = array_merge(...array_values($this->catalog->getCategoryIds($seeds)));
        $seedCategories = array_values(array_unique(array_merge($seedCategories, $context->productCategoryIds)));

        $related = $this->relation->getRelated($context->storeId, Relation::CO_PURCHASE, $seeds, $size * 2);
        if ($related) {
            $categories = $this->catalog->getCategoryIds(array_keys($related));
            foreach ($related as $productId => $score) {
                // complements live in different categories than the seed products
                if (!array_intersect($categories[$productId] ?? [], $seedCategories)) {
                    $set->add((int)$productId, 'behavior', (float)$score, 'complementary');
                }
            }
            $set->normalize('behavior');
        }

        $targets = $this->map['*'] ?? [];
        foreach ($seedCategories as $categoryId) {
            $targets = array_merge($targets, $this->map[(string)$categoryId] ?? []);
        }
        $targets = array_values(array_unique($targets));
        if ($targets) {
            $set->addMany($this->productStat->getTop($context->storeId, 'popularity_score', $size, $targets), 'behavior', 'complementary', 0.7);
            if ($set->count() < $limit) {
                $set->addMany($this->catalog->getNewest($context->websiteId, $size, $targets), 'newness', 'complementary', 0.5);
            }
        }
        return $set;
    }
}
