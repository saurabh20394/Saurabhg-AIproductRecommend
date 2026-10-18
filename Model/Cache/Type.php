<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Cache;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

/**
 * "AI Recommendations" cache type (results, unit lists, health state)
 */
class Type extends TagScope
{
    public const TYPE_IDENTIFIER = 'ai_recommendation';
    public const CACHE_TAG = 'AI_RECOMMENDATION';

    /**
     * All generated recommendation results
     */
    public const TAG_RESULTS = 'AIPR_RESULTS';

    /**
     * Results containing / generated for a product: prefix + product ID
     */
    public const TAG_PRODUCT_PREFIX = 'AIPR_P_';

    /**
     * @param FrontendPool $cacheFrontendPool
     */
    public function __construct(FrontendPool $cacheFrontendPool)
    {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}
