<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Block\Adminhtml\Edit;

use Magento\Backend\Block\Widget\Context;

/**
 * Shared helpers of edit form buttons (URLs relative to the current admin controller)
 */
class GenericButton
{
    /**
     * @param Context $context
     */
    public function __construct(
        protected readonly Context $context
    ) {
    }

    /**
     * @return int
     */
    public function getEntityId(): int
    {
        return (int)$this->context->getRequest()->getParam('id');
    }

    /**
     * @param string $route
     * @param array $params
     * @return string
     */
    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
