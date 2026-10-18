<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Block\Widget;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Widget\Block\BlockInterface;
use Saurabhg\AIproductRecommend\Api\UnitRepositoryInterface;
use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\Unit;

/**
 * CMS widget "AI Product Recommendations": placeholder of one unit, lazy-loaded on the storefront
 */
class Recommendation extends Template implements BlockInterface, IdentityInterface
{
    /**
     * @var string
     */
    protected $_template = 'Saurabhg_AIproductRecommend::placement.phtml';

    /**
     * @param Context $context
     * @param UnitRepositoryInterface $unitRepository
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly UnitRepositoryInterface $unitRepository,
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
        $storeId = (int)$this->_storeManager->getStore()->getId();
        $code = (string)$this->getData('unit_code');
        if ($code === '' || !$this->config->isEnabled($storeId)) {
            return [];
        }
        try {
            /** @var Unit $unit */
            $unit = $this->unitRepository->getByCode($code);
        } catch (NoSuchEntityException $e) {
            return [];
        }
        return $unit->isActive() && $unit->isForStore($storeId) ? [$unit] : [];
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
