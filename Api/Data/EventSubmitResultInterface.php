<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Api\Data;

/**
 * Event submission outcome (events are processed asynchronously)
 *
 * @api
 */
interface EventSubmitResultInterface
{
    /**
     * @return int
     */
    public function getAccepted(): int;

    /**
     * @return int
     */
    public function getRejected(): int;

    /**
     * @return string[]
     */
    public function getErrors(): array;
}
