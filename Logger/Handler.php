<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger as Monolog;

/**
 * var/log/ai_recommend.log
 */
class Handler extends Base
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/ai_recommend.log';

    /**
     * @var int
     */
    protected $loggerType = Monolog::DEBUG;
}
