<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\System\Store as SystemStore;

/**
 * Store views grouped by website, including "All Store Views" (0)
 */
class StoreView implements OptionSourceInterface
{
    /**
     * @param SystemStore $systemStore
     */
    public function __construct(
        private readonly SystemStore $systemStore
    ) {
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return $this->systemStore->getStoreValuesForForm(false, true);
    }
}
