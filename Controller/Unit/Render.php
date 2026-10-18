<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Unit;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\LayoutFactory;
use Magento\Store\Model\StoreManagerInterface;
use Saurabhg\AIproductRecommend\Block\Product\ListRenderer;
use Saurabhg\AIproductRecommend\Logger\Logger;
use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\Recommendation\ContextBuilder;
use Saurabhg\AIproductRecommend\Model\Recommendation\ProductHydrator;
use Saurabhg\AIproductRecommend\Model\Recommendation\RecommendationService;

/**
 * Lazy, batched rendering of recommendation units (private, never full-page cached).
 * Responses contain rendered HTML plus SKU / position for impression tracking; scores are never exposed.
 */
class Render implements HttpGetActionInterface
{
    private const MAX_UNITS = 10;

    /**
     * @param RequestInterface $request
     * @param JsonFactory $jsonFactory
     * @param ContextBuilder $contextBuilder
     * @param RecommendationService $service
     * @param ProductHydrator $hydrator
     * @param LayoutFactory $layoutFactory
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param Logger $logger
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly ContextBuilder $contextBuilder,
        private readonly RecommendationService $service,
        private readonly ProductHydrator $hydrator,
        private readonly LayoutFactory $layoutFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly Logger $logger
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $response = $this->jsonFactory->create();
        $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $response->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
        $storeId = (int)$this->storeManager->getStore()->getId();
        if (!$this->config->isEnabled($storeId)) {
            return $response->setData(['units' => new \stdClass()]);
        }
        $codes = array_values(array_unique(array_filter(
            array_map('strval', (array)$this->request->getParam('codes', [])),
            static fn($code) => (bool)preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $code)
        )));
        $codes = array_slice($codes, 0, self::MAX_UNITS);
        if (!$codes) {
            return $response->setData(['units' => new \stdClass()]);
        }

        $units = [];
        try {
            $context = $this->contextBuilder->fromStorefront([
                'page_type' => (string)$this->request->getParam('page_type', 'other'),
                'product_id' => $this->request->getParam('product_id') ? (int)$this->request->getParam('product_id') : null,
                'category_id' => $this->request->getParam('category_id') ? (int)$this->request->getParam('category_id') : null,
            ]);
            $renderer = $this->getRenderer();
            foreach ($codes as $code) {
                $context->unitCode = $code;
                $units[$code] = $this->renderUnit($context, $renderer, $storeId);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Recommendation rendering failed', ['error' => $e]);
        }
        return $response->setData(['units' => $units ?: new \stdClass()]);
    }

    /**
     * @param \Saurabhg\AIproductRecommend\Model\Recommendation\Context $context
     * @param ListRenderer $renderer
     * @param int $storeId
     * @return array
     */
    private function renderUnit($context, ListRenderer $renderer, int $storeId): array
    {
        $result = $this->service->recommend($context);
        $items = $result->getItems();
        $products = $this->hydrator->loadProducts(array_map(static fn($item) => $item->getProductId(), $items), $storeId);
        if (!$products) {
            return ['html' => '', 'empty' => true];
        }
        $visible = [];
        foreach ($items as $item) {
            if (isset($products[$item->getProductId()])) {
                $item->setData('position', count($visible) + 1);
                $visible[] = $item;
            }
        }
        $html = $renderer->setData('result', $result)
            ->setData('items', $visible)
            ->setData('products', $products)
            ->toHtml();
        return [
            'html' => $html,
            'empty' => false,
            'token' => $result->getTrackingToken(),
            'items' => array_map(static fn($item) => ['sku' => $item->getSku(), 'position' => $item->getPosition()], $visible),
        ];
    }

    /**
     * Isolated layout providing the standard product price renderer
     *
     * @return ListRenderer
     */
    private function getRenderer(): ListRenderer
    {
        $layout = $this->layoutFactory->create();
        $layout->getUpdate()->load(['airecommend_unit_render']);
        $layout->generateXml();
        $layout->generateElements();
        /** @var ListRenderer $block */
        $block = $layout->getBlock('airecommend.unit.renderer');
        return $block;
    }
}
