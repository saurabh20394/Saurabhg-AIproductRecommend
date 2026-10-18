<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Block\Product;

use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Product;
use Magento\Framework\Url\Helper\Data as UrlHelper;
use Saurabhg\AIproductRecommend\Api\Data\RecommendationItemInterface;
use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\Data\RecommendationResult;

/**
 * Renders a recommendation unit with Magento product rendering conventions (image, price box, add to cart)
 */
class ListRenderer extends AbstractProduct
{
    /**
     * @var string
     */
    protected $_template = 'Saurabhg_AIproductRecommend::unit/list.phtml';

    /**
     * @param Context $context
     * @param UrlHelper $urlHelper
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly UrlHelper $urlHelper,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return RecommendationResult|null
     */
    public function getResult(): ?RecommendationResult
    {
        $result = $this->getData('result');
        return $result instanceof RecommendationResult ? $result : null;
    }

    /**
     * @return RecommendationItemInterface[]
     */
    public function getItems(): array
    {
        return (array)$this->getData('items');
    }

    /**
     * @param RecommendationItemInterface $item
     * @return Product|null
     */
    public function getItemProduct(RecommendationItemInterface $item): ?Product
    {
        return $this->getData('products')[$item->getProductId()] ?? null;
    }

    /**
     * @return string
     */
    public function getUnitTitle(): string
    {
        $result = $this->getResult();
        return $result && $result->getTitle() ? $result->getTitle() : (string)__('Recommended for you');
    }

    /**
     * @return bool
     */
    public function canShowReason(): bool
    {
        $result = $this->getResult();
        return $this->config->isSetFlag('storefront/show_reason') && $result && $result->getData('show_reason');
    }

    /**
     * @param Product $product
     * @return bool
     */
    public function canAddToCart(Product $product): bool
    {
        return $this->config->isSetFlag('storefront/show_add_to_cart')
            && $product->isSaleable()
            && $product->getTypeInstance()->isPossibleBuyFromList($product);
    }

    /**
     * @param Product $product
     * @return array{action: string, data: array}
     */
    public function getAddToCartPostParams(Product $product): array
    {
        $url = $this->getAddToCartUrl($product, ['_escape' => false]);
        return [
            'action' => $url,
            'data' => [
                'product' => (int)$product->getEntityId(),
                \Magento\Framework\App\ActionInterface::PARAM_NAME_URL_ENCODED => $this->urlHelper->getEncodedUrl($url),
            ],
        ];
    }

    /**
     * Stable DOM id for the unit heading
     *
     * @return string
     */
    public function getHeadingId(): string
    {
        $result = $this->getResult();
        return 'aipr-title-' . preg_replace('/[^a-z0-9_\-]/i', '', (string)($result ? $result->getUnitCode() : 'unit'));
    }
}
