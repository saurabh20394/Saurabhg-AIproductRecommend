<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Recommendation\Strategy;

use Saurabhg\AIproductRecommend\Model\Source\UnitType;

/**
 * Unit type => strategy; additional strategies can be registered through di.xml
 */
class StrategyPool
{
    /**
     * @param StrategyInterface[] $strategies
     */
    public function __construct(
        private readonly array $strategies = []
    ) {
    }

    /**
     * @param string $type
     * @return StrategyInterface
     */
    public function get(string $type): StrategyInterface
    {
        return $this->strategies[$type] ?? $this->strategies[UnitType::POPULAR];
    }

    /**
     * @param string $type
     * @return bool
     */
    public function has(string $type): bool
    {
        return isset($this->strategies[$type]);
    }
}
