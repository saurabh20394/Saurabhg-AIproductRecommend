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
use Saurabhg\AIproductRecommend\Api\Data\RecommendationResultInterface;

/**
 * Recommendation response; "debug", "experiment_id" and "variant_id" are internal keys
 */
class RecommendationResult extends DataObject implements RecommendationResultInterface
{
    /**
     * @inheritdoc
     */
    public function getUnitCode(): ?string
    {
        $value = $this->getData('unit_code');
        return $value === null ? null : (string)$value;
    }

    /**
     * @inheritdoc
     */
    public function getTitle(): ?string
    {
        $value = $this->getData('title');
        return $value === null ? null : (string)$value;
    }

    /**
     * @inheritdoc
     */
    public function getType(): string
    {
        return (string)$this->getData('type');
    }

    /**
     * @inheritdoc
     */
    public function getSource(): string
    {
        return (string)$this->getData('source');
    }

    /**
     * @inheritdoc
     */
    public function getModelVersion(): ?string
    {
        $value = $this->getData('model_version');
        return $value === null || $value === '' ? null : (string)$value;
    }

    /**
     * @inheritdoc
     */
    public function getIsFallback(): bool
    {
        return (bool)$this->getData('is_fallback');
    }

    /**
     * @inheritdoc
     */
    public function getRequestId(): string
    {
        return (string)$this->getData('request_id');
    }

    /**
     * @inheritdoc
     */
    public function getTrackingToken(): ?string
    {
        $value = $this->getData('tracking_token');
        return $value === null ? null : (string)$value;
    }

    /**
     * @inheritdoc
     */
    public function getItems(): array
    {
        return (array)$this->getData('items');
    }
}
