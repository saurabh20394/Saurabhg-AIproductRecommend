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
use Saurabhg\AIproductRecommend\Model\ResourceModel\Unit\CollectionFactory;

/**
 * Recommendation unit codes
 */
class UnitCode implements OptionSourceInterface
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
        foreach ($this->collectionFactory->create()->setOrder('code', 'ASC') as $unit) {
            $options[] = [
                'value' => (string)$unit->getData('code'),
                'label' => $unit->getData('code') . ' - ' . $unit->getData('title'),
            ];
        }
        return $options;
    }
}
