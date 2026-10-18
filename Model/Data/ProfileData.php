<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Data;

use Magento\Framework\DataObject;
use Saurabhg\AIproductRecommend\Api\Data\ProfileInterface;

/**
 * Aggregate profile DTO
 */
class ProfileData extends DataObject implements ProfileInterface
{
    /**
     * @inheritdoc
     */
    public function getCustomerId(): ?int
    {
        return $this->getData('customer_id') !== null ? (int)$this->getData('customer_id') : null;
    }

    /**
     * @inheritdoc
     */
    public function getProfileType(): string
    {
        return (string)$this->getData('profile_type');
    }

    /**
     * @inheritdoc
     */
    public function getEventCount(): int
    {
        return (int)$this->getData('event_count');
    }

    /**
     * @inheritdoc
     */
    public function getTopCategoryIds(): array
    {
        return array_map('intval', (array)$this->getData('top_category_ids'));
    }

    /**
     * @inheritdoc
     */
    public function getTopBrands(): array
    {
        return array_map('strval', (array)$this->getData('top_brands'));
    }

    /**
     * @inheritdoc
     */
    public function getAverageViewedPrice(): ?float
    {
        return $this->getData('average_viewed_price') !== null ? (float)$this->getData('average_viewed_price') : null;
    }

    /**
     * @inheritdoc
     */
    public function getOrdersCount(): int
    {
        return (int)$this->getData('orders_count');
    }

    /**
     * @inheritdoc
     */
    public function getRecentSkus(): array
    {
        return array_map('strval', (array)$this->getData('recent_skus'));
    }

    /**
     * @inheritdoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData('updated_at') !== null ? (string)$this->getData('updated_at') : null;
    }
}
