<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\ViewModel;

use Magento\Framework\App\Request\Http;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\Personalization\ConsentChecker;

/**
 * Page context and tracker configuration for the storefront (full page cache safe: per-URL data only,
 * no customer data)
 */
class Tracker implements ArgumentInterface
{
    private const PAGE_TYPES = [
        'cms_index_index' => 'home',
        'catalog_category_view' => 'category',
        'catalog_product_view' => 'product',
        'checkout_cart_index' => 'cart',
        'checkout_onepage_success' => 'success',
        'multishipping_checkout_success' => 'success',
        'catalogsearch_result_index' => 'search',
    ];

    /**
     * @param Http $request
     * @param UrlInterface $urlBuilder
     * @param StoreManagerInterface $storeManager
     * @param ConsentChecker $consentChecker
     * @param Config $config
     */
    public function __construct(
        private readonly Http $request,
        private readonly UrlInterface $urlBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly ConsentChecker $consentChecker,
        private readonly Config $config
    ) {
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled((int)$this->storeManager->getStore()->getId());
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $pageType = self::PAGE_TYPES[$this->request->getFullActionName()] ?? 'other';
        $config = [
            'collectUrl' => $this->urlBuilder->getUrl('airecommend/event/collect', ['_secure' => $this->request->isSecure()]),
            'renderUrl' => $this->urlBuilder->getUrl('airecommend/unit/render', ['_secure' => $this->request->isSecure()]),
            'pageType' => $pageType,
            'productId' => $pageType === 'product' ? (int)$this->request->getParam('id') : null,
            'categoryId' => $pageType === 'category' ? (int)$this->request->getParam('id') : null,
            'searchQuery' => null,
            'tracking' => $this->config->isTrackingEnabled($storeId),
            'trackAnonymous' => $this->config->isSetFlag('tracking/anonymous', $storeId),
            'trackCategory' => $this->config->isSetFlag('tracking/track_category', $storeId),
            'trackSearch' => $this->config->isSetFlag('tracking/track_search', $storeId),
            'maxBatch' => max(1, $this->config->getInt('tracking/max_batch', null, 50)),
            'visitorCookie' => Config::VISITOR_COOKIE,
            'consent' => $this->consentChecker->getClientConfig($storeId),
        ];
        if ($pageType === 'search' && $config['trackSearch']) {
            $config['searchQuery'] = mb_substr(trim(strip_tags((string)$this->request->getParam('q'))), 0, 128);
        }
        return $config;
    }
}
