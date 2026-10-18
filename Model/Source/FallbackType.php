<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Source;

/**
 * Unit fallback strategies
 */
class FallbackType extends AbstractOptions
{
    public const NONE = 'none';

    /**
     * @inheritdoc
     */
    public function getOptions(): array
    {
        return [
            UnitType::POPULAR => __('Popular Products'),
            UnitType::TRENDING => __('Trending Products'),
            UnitType::NEWEST => __('New Arrivals'),
            self::NONE => __('Hide Unit'),
        ];
    }
}
