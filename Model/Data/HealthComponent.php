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
use Saurabhg\AIproductRecommend\Api\Data\HealthComponentInterface;

/**
 * Dependency health DTO
 */
class HealthComponent extends DataObject implements HealthComponentInterface
{
    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return (string)$this->getData('name');
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): string
    {
        return (string)$this->getData('status');
    }

    /**
     * @inheritdoc
     */
    public function getMessage(): string
    {
        return (string)$this->getData('message');
    }
}
