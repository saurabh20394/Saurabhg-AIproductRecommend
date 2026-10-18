<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Api;

use Magento\Framework\Exception\NoSuchEntityException;
use Saurabhg\AIproductRecommend\Api\Data\ProfileInterface;
use Saurabhg\AIproductRecommend\Api\ProfileManagementInterface;
use Saurabhg\AIproductRecommend\Model\Data\ProfileDataFactory;
use Saurabhg\AIproductRecommend\Model\Personalization\ProfileService;
use Saurabhg\AIproductRecommend\Model\Privacy\DataEraser;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Catalog;

/**
 * Profile access (ACL / self-scoped routes in webapi.xml) and erasure
 */
class ProfileManagement implements ProfileManagementInterface
{
    /**
     * @param ProfileService $profileService
     * @param ProfileDataFactory $profileDataFactory
     * @param Catalog $catalog
     * @param DataEraser $dataEraser
     */
    public function __construct(
        private readonly ProfileService $profileService,
        private readonly ProfileDataFactory $profileDataFactory,
        private readonly Catalog $catalog,
        private readonly DataEraser $dataEraser
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getProfile(int $id): ProfileInterface
    {
        $profile = $this->profileService->load($id, null);
        if (!$profile) {
            throw new NoSuchEntityException(__('No recommendation profile exists for this customer.'));
        }
        $features = $this->profileService->decay($profile->getFeatures());
        $recent = array_slice(array_map('intval', $features['recent'] ?? []), 0, 10);
        $skus = $this->catalog->getSkus($recent);
        return $this->profileDataFactory->create(['data' => [
            'customer_id' => $id,
            'profile_type' => (string)$profile->getData('profile_type'),
            'event_count' => (int)$profile->getData('event_count'),
            'top_category_ids' => array_map('intval', ProfileService::top($features, 'cat', 5)),
            'top_brands' => ProfileService::top($features, 'brand', 5),
            'average_viewed_price' => !empty($features['price_n'])
                ? round((float)$features['price_sum'] / (int)$features['price_n'], 2) : null,
            'orders_count' => (int)($features['orders'] ?? 0),
            'recent_skus' => array_values(array_filter(array_map(static fn($id) => $skus[$id] ?? null, $recent))),
            'updated_at' => (string)$profile->getData('updated_at'),
        ]]);
    }

    /**
     * @inheritdoc
     */
    public function deleteProfile(int $id): bool
    {
        $this->dataEraser->forgetCustomer($id);
        return true;
    }
}
