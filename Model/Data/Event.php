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
use Saurabhg\AIproductRecommend\Api\Data\EventInterface;

/**
 * Event DTO
 */
class Event extends DataObject implements EventInterface
{
    /**
     * @inheritdoc
     */
    public function getEventId(): ?string
    {
        return $this->getData('event_id') !== null ? (string)$this->getData('event_id') : null;
    }

    /**
     * @inheritdoc
     */
    public function setEventId(?string $eventId)
    {
        return $this->setData('event_id', $eventId);
    }

    /**
     * @inheritdoc
     */
    public function getEventType(): string
    {
        return (string)$this->getData('event_type');
    }

    /**
     * @inheritdoc
     */
    public function setEventType(string $eventType)
    {
        return $this->setData('event_type', $eventType);
    }

    /**
     * @inheritdoc
     */
    public function getTimestamp(): ?int
    {
        return $this->getData('timestamp') !== null ? (int)$this->getData('timestamp') : null;
    }

    /**
     * @inheritdoc
     */
    public function setTimestamp(?int $timestamp)
    {
        return $this->setData('timestamp', $timestamp);
    }

    /**
     * @inheritdoc
     */
    public function getSku(): ?string
    {
        return $this->getData('sku') !== null ? (string)$this->getData('sku') : null;
    }

    /**
     * @inheritdoc
     */
    public function setSku(?string $sku)
    {
        return $this->setData('sku', $sku);
    }

    /**
     * @inheritdoc
     */
    public function getProductId(): ?int
    {
        return $this->getData('product_id') !== null ? (int)$this->getData('product_id') : null;
    }

    /**
     * @inheritdoc
     */
    public function setProductId(?int $productId)
    {
        return $this->setData('product_id', $productId);
    }

    /**
     * @inheritdoc
     */
    public function getCategoryId(): ?int
    {
        return $this->getData('category_id') !== null ? (int)$this->getData('category_id') : null;
    }

    /**
     * @inheritdoc
     */
    public function setCategoryId(?int $categoryId)
    {
        return $this->setData('category_id', $categoryId);
    }

    /**
     * @inheritdoc
     */
    public function getQuery(): ?string
    {
        return $this->getData('query') !== null ? (string)$this->getData('query') : null;
    }

    /**
     * @inheritdoc
     */
    public function setQuery(?string $query)
    {
        return $this->setData('query', $query);
    }

    /**
     * @inheritdoc
     */
    public function getQuantity(): ?float
    {
        return $this->getData('quantity') !== null ? (float)$this->getData('quantity') : null;
    }

    /**
     * @inheritdoc
     */
    public function setQuantity(?float $quantity)
    {
        return $this->setData('quantity', $quantity);
    }

    /**
     * @inheritdoc
     */
    public function getVisitorId(): ?string
    {
        return $this->getData('visitor_id') !== null ? (string)$this->getData('visitor_id') : null;
    }

    /**
     * @inheritdoc
     */
    public function setVisitorId(?string $visitorId)
    {
        return $this->setData('visitor_id', $visitorId);
    }

    /**
     * @inheritdoc
     */
    public function getUnitCode(): ?string
    {
        return $this->getData('unit_code') !== null ? (string)$this->getData('unit_code') : null;
    }

    /**
     * @inheritdoc
     */
    public function setUnitCode(?string $unitCode)
    {
        return $this->setData('unit_code', $unitCode);
    }

    /**
     * @inheritdoc
     */
    public function getPosition(): ?int
    {
        return $this->getData('position') !== null ? (int)$this->getData('position') : null;
    }

    /**
     * @inheritdoc
     */
    public function setPosition(?int $position)
    {
        return $this->setData('position', $position);
    }

    /**
     * @inheritdoc
     */
    public function getTrackingToken(): ?string
    {
        return $this->getData('tracking_token') !== null ? (string)$this->getData('tracking_token') : null;
    }

    /**
     * @inheritdoc
     */
    public function setTrackingToken(?string $trackingToken)
    {
        return $this->setData('tracking_token', $trackingToken);
    }
}
