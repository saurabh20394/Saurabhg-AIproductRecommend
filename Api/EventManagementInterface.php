<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Api;

use Saurabhg\AIproductRecommend\Api\Data\EventSubmitResultInterface;

/**
 * Asynchronous behavior / recommendation event ingestion (rate limited)
 *
 * @api
 */
interface EventManagementInterface
{
    /**
     * @param \Saurabhg\AIproductRecommend\Api\Data\EventInterface[] $events
     * @return \Saurabhg\AIproductRecommend\Api\Data\EventSubmitResultInterface
     */
    public function submit(array $events): EventSubmitResultInterface;
}
