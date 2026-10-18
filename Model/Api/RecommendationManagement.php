<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Api;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Saurabhg\AIproductRecommend\Api\Data\RecommendationResultInterface;
use Saurabhg\AIproductRecommend\Api\RecommendationManagementInterface;
use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\Recommendation\Context;
use Saurabhg\AIproductRecommend\Model\Recommendation\ContextBuilder;
use Saurabhg\AIproductRecommend\Model\Recommendation\ProductHydrator;
use Saurabhg\AIproductRecommend\Model\Recommendation\RecommendationService;
use Saurabhg\AIproductRecommend\Model\Source\UnitType;

/**
 * REST recommendation endpoints
 */
class RecommendationManagement implements RecommendationManagementInterface
{
    /**
     * @param ContextBuilder $contextBuilder
     * @param RecommendationService $service
     * @param ProductHydrator $hydrator
     * @param UserContextInterface $userContext
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     */
    public function __construct(
        private readonly ContextBuilder $contextBuilder,
        private readonly RecommendationService $service,
        private readonly ProductHydrator $hydrator,
        private readonly UserContextInterface $userContext,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getRecommendations(
        ?string $unitCode = null,
        ?string $type = null,
        ?string $productSku = null,
        ?int $categoryId = null,
        ?array $cartSkus = null,
        ?string $visitorId = null,
        ?string $pageType = null,
        ?int $limit = null,
        ?int $customerGroupId = null
    ): RecommendationResultInterface {
        return $this->run([
            'unit_code' => $unitCode,
            'type' => $unitCode ? null : ($type ?: UnitType::HYBRID),
            'product_sku' => $productSku,
            'category_id' => $categoryId,
            'cart_skus' => $cartSkus ?? [],
            'visitor_id' => $visitorId,
            'page_type' => $pageType ?: 'api',
            'limit' => $limit,
        ], $customerGroupId);
    }

    /**
     * @inheritdoc
     */
    public function getUnit(
        string $code,
        ?string $productSku = null,
        ?int $categoryId = null,
        ?array $cartSkus = null,
        ?string $visitorId = null,
        ?int $limit = null
    ): RecommendationResultInterface {
        return $this->run([
            'unit_code' => $code,
            'product_sku' => $productSku,
            'category_id' => $categoryId,
            'cart_skus' => $cartSkus ?? [],
            'visitor_id' => $visitorId,
            'limit' => $limit,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getSimilar(string $sku, ?int $limit = null): RecommendationResultInterface
    {
        return $this->run(['type' => UnitType::SIMILAR, 'product_sku' => $sku, 'page_type' => 'product', 'limit' => $limit]);
    }

    /**
     * @inheritdoc
     */
    public function getTrending(?int $limit = null, ?int $categoryId = null): RecommendationResultInterface
    {
        return $this->run(['type' => UnitType::TRENDING, 'category_id' => $categoryId, 'limit' => $limit]);
    }

    /**
     * @param array $params
     * @param int|null $customerGroupId
     * @return RecommendationResultInterface
     * @throws LocalizedException
     */
    private function run(array $params, ?int $customerGroupId = null): RecommendationResultInterface
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $params['store_id'] = $storeId;
        $userType = (int)$this->userContext->getUserType();
        if ($userType === UserContextInterface::USER_TYPE_CUSTOMER
            && $this->config->isSetFlag('tracking/personalize_customers', $storeId)
        ) {
            $params['customer_id'] = (int)$this->userContext->getUserId();
        }
        if ($customerGroupId !== null && in_array($userType, [
            UserContextInterface::USER_TYPE_ADMIN, UserContextInterface::USER_TYPE_INTEGRATION,
        ], true)) {
            $params['customer_group_id'] = $customerGroupId;
        }
        $context = $this->contextBuilder->create(array_filter($params, static fn($v) => $v !== null), Context::CHANNEL_REST);
        $result = $this->service->recommend($context);
        $this->hydrator->hydrate($result, $storeId);
        return $result;
    }
}
