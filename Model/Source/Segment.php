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
use Saurabhg\AIproductRecommend\Model\ResourceModel\Segment\CollectionFactory;

/**
 * Customer segments
 */
class Segment implements OptionSourceInterface
{
    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->collectionFactory->create()->setOrder('name', 'ASC') as $segment) {
            $label = (string)$segment->getData('name');
            if (!(int)$segment->getData('status')) {
                $label .= ' (' . __('disabled') . ')';
            }
            $options[] = ['value' => (string)$segment->getId(), 'label' => $label];
        }
        return $options;
    }
}
