<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Api;

/**
 * Health of the recommendation dependencies
 *
 * @api
 */
interface HealthCheckInterface
{
    /**
     * @return \Saurabhg\AIproductRecommend\Api\Data\HealthComponentInterface[]
     */
    public function check(): array;
}
