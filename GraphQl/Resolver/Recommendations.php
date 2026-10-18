<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\GraphQl\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\Recommendation\Context;
use Saurabhg\AIproductRecommend\Model\Recommendation\ContextBuilder;
use Saurabhg\AIproductRecommend\Model\Recommendation\ProductHydrator;
use Saurabhg\AIproductRecommend\Model\Recommendation\RecommendationService;
use Saurabhg\AIproductRecommend\Model\Source\UnitType;

/**
 * Query.aiRecommendations: no internal identifiers or private features are exposed
 */
class Recommendations implements ResolverInterface
{
    /**
     * @param ContextBuilder $contextBuilder
     * @param RecommendationService $service
     * @param ProductHydrator $hydrator
     * @param Config $config
     */
    public function __construct(
        private readonly ContextBuilder $contextBuilder,
        private readonly RecommendationService $service,
        private readonly ProductHydrator $hydrator,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        /** @var ContextInterface $context */
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $params = [
            'store_id' => $storeId,
            'unit_code' => $args['unitCode'] ?? null,
            'type' => empty($args['unitCode']) ? ($args['type'] ?? UnitType::HYBRID) : null,
            'product_sku' => $args['productSku'] ?? null,
            'category_id' => $args['categoryId'] ?? null,
            'cart_skus' => $args['cartSkus'] ?? [],
            'visitor_id' => $args['visitorId'] ?? null,
            'page_type' => $args['pageType'] ?? 'api',
            'limit' => $args['limit'] ?? null,
        ];
        if ($context->getExtensionAttributes()->getIsCustomer()
            && $this->config->isSetFlag('tracking/personalize_customers', $storeId)
        ) {
            $params['customer_id'] = (int)$context->getUserId();
        }
        try {
            $recommendationContext = $this->contextBuilder->create(
                array_filter($params, static fn($v) => $v !== null),
                Context::CHANNEL_GRAPHQL
            );
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(__($e->getMessage()), $e);
        }
        $result = $this->service->recommend($recommendationContext);
        $products = $this->hydrator->loadProducts(
            array_map(static fn($item) => $item->getProductId(), $result->getItems()),
            $storeId,
            true
        );
        $output = [];
        foreach ($result->getItems() as $item) {
            $product = $products[$item->getProductId()] ?? null;
            if (!$product) {
                continue;
            }
            $output[] = [
                'sku' => $item->getSku(),
                'position' => count($output) + 1,
                'score' => $item->getScore(),
                'reason' => $item->getReasonCode(),
                'reasonLabel' => $item->getReasonLabel(),
                'product' => $product->getData() + ['model' => $product],
            ];
        }
        return [
            'unitCode' => $result->getUnitCode(),
            'title' => $result->getTitle(),
            'type' => $result->getType(),
            'source' => $result->getSource(),
            'isFallback' => $result->getIsFallback(),
            'requestId' => $result->getRequestId(),
            'trackingToken' => $result->getTrackingToken(),
            'products' => $output,
        ];
    }
}
