<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * "Validate Connection" button: embeds a sample catalog text with the saved provider configuration
 */
class TestConnection extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Saurabhg_AIproductRecommend::system/config/test_connection.phtml';

    /**
     * @inheritdoc
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * @inheritdoc
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * @return string
     */
    public function getAjaxUrl(): string
    {
        $params = [];
        foreach (['website', 'store'] as $scope) {
            if ($this->getRequest()->getParam($scope)) {
                $params[$scope] = $this->getRequest()->getParam($scope);
            }
        }
        return $this->getUrl('saurabhg_airecommend/config/testConnection', $params);
    }
}
