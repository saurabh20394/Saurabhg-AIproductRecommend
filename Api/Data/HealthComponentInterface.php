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
 * Dependency health state (never contains secrets)
 *
 * @api
 */
interface HealthComponentInterface
{
    public const STATUS_OK = 'ok';
    public const STATUS_WARNING = 'warning';
    public const STATUS_DOWN = 'down';
    public const STATUS_DISABLED = 'disabled';

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * ok / warning / down / disabled
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * @return string
     */
    public function getMessage(): string;
}
