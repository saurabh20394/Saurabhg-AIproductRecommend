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
use Saurabhg\AIproductRecommend\Api\Data\RecommendationItemInterface;

/**
 * Recommended product; internal keys (components, final_score, product model) are not part of the API
 */
class RecommendationItem extends DataObject implements RecommendationItemInterface
{
    /**
     * @inheritdoc
     */
    public function getSku(): string
    {
        return (string)$this->getData('sku');
    }

    /**
     * @inheritdoc
     */
    public function getProductId(): int
    {
        return (int)$this->getData('product_id');
    }

    /**
     * @inheritdoc
     */
    public function getName(): ?string
    {
        $value = $this->getData('name');
        return $value === null ? null : (string)$value;
    }

    /**
     * @inheritdoc
     */
    public function getUrl(): ?string
    {
        $value = $this->getData('url');
        return $value === null ? null : (string)$value;
    }

    /**
     * @inheritdoc
     */
    public function getPrice(): ?float
    {
        $value = $this->getData('price');
        return $value === null ? null : (float)$value;
    }

    /**
     * @inheritdoc
     */
    public function getImageUrl(): ?string
    {
        $value = $this->getData('image_url');
        return $value === null ? null : (string)$value;
    }

    /**
     * @inheritdoc
     */
    public function getPosition(): int
    {
        return (int)$this->getData('position');
    }

    /**
     * @inheritdoc
     */
    public function getScore(): ?float
    {
        $value = $this->getData('score');
        return $value === null ? null : (float)$value;
    }

    /**
     * @inheritdoc
     */
    public function getReasonCode(): string
    {
        return (string)$this->getData('reason_code');
    }

    /**
     * @inheritdoc
     */
    public function getReasonLabel(): string
    {
        return (string)$this->getData('reason_label');
    }
}
