<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Block;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Unit\CollectionFactory;
use Saurabhg\AIproductRecommend\Model\Unit;

/**
 * Full-page-cache-safe placeholders of the units configured for a placement; content is lazy-loaded.
 * Layout argument "placement": placement code (see Model\Source\Placement).
 */
class Placement extends Template implements IdentityInterface
{
    /**
     * @var string
     */
    protected $_template = 'Saurabhg_AIproductRecommend::placement.phtml';

    /**
     * @var Unit[]|null
     */
    private ?array $units = null;

    /**
     * @param Context $context
     * @param CollectionFactory $collectionFactory
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly CollectionFactory $collectionFactory,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return Unit[]
     */
    public function getUnits(): array
    {
        if ($this->units === null) {
            $this->units = [];
            $storeId = (int)$this->_storeManager->getStore()->getId();
            if (!$this->config->isEnabled($storeId) || !$this->getData('placement')) {
                return $this->units;
            }
            $collection = $this->collectionFactory->create()
                ->addFieldToFilter('status', 1)
                ->addFieldToFilter('placement', (string)$this->getData('placement'))
                ->setOrder('position', 'ASC')
                ->setOrder('entity_id', 'ASC');
            /** @var Unit $unit */
            foreach ($collection as $unit) {
                if ($unit->isForStore($storeId)) {
                    $this->units[] = $unit;
                }
            }
        }
        return $this->units;
    }

    /**
     * @inheritdoc
     */
    public function getIdentities()
    {
        return [Unit::CACHE_TAG_UNITS];
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml()
    {
        return $this->getUnits() ? parent::_toHtml() : '';
    }
}
