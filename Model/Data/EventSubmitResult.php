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
use Saurabhg\AIproductRecommend\Api\Data\EventSubmitResultInterface;

/**
 * Event submission outcome
 */
class EventSubmitResult extends DataObject implements EventSubmitResultInterface
{
    /**
     * @inheritdoc
     */
    public function getAccepted(): int
    {
        return (int)$this->getData('accepted');
    }

    /**
     * @inheritdoc
     */
    public function getRejected(): int
    {
        return (int)$this->getData('rejected');
    }

    /**
     * @inheritdoc
     */
    public function getErrors(): array
    {
        return array_values((array)$this->getData('errors'));
    }
}
