<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Starter recommendation units (the module itself stays disabled until enabled in configuration)
 */
class CreateDefaultUnits implements DataPatchInterface
{
    private const UNITS = [
        ['home_trending', 'Trending Now', 'trending', 'home_top', 10, 8, 'popular'],
        ['home_for_you', 'Recommended For You', 'recommended_for_you', 'home_bottom', 20, 8, 'popular'],
        ['category_popular', 'Popular in This Category', 'popular', 'category_bottom', 10, 6, 'newest'],
        ['pdp_similar', 'Similar Products', 'similar', 'product_info', 10, 6, 'popular'],
        ['pdp_fbt', 'Frequently Bought Together', 'frequently_bought_together', 'product_info', 20, 4, 'none'],
        ['pdp_also_viewed', 'Customers Also Viewed', 'also_viewed', 'product_bottom', 10, 6, 'none'],
        ['pdp_recently_viewed', 'Recently Viewed', 'recently_viewed', 'product_bottom', 20, 6, 'none'],
        ['cart_complete', 'Complete Your Order', 'hybrid', 'cart_bottom', 10, 6, 'popular'],
        ['success_complementary', 'You May Also Like', 'complementary', 'success_bottom', 10, 6, 'popular'],
    ];

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('ai_recommendation_unit');
        $existing = $connection->fetchCol($connection->select()->from($table, ['code']));
        $rows = [];
        foreach (self::UNITS as [$code, $title, $type, $placement, $position, $count, $fallback]) {
            if (in_array($code, $existing, true)) {
                continue;
            }
            $rows[] = [
                'code' => $code,
                'title' => $title,
                'type' => $type,
                'status' => 1,
                'store_ids' => '0',
                'placement' => $placement,
                'position' => $position,
                'product_count' => $count,
                'min_score' => 0,
                'fallback_type' => $fallback,
                'show_reason' => 1,
            ];
        }
        if ($rows) {
            $connection->insertMultiple($table, $rows);
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
