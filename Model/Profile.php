<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Derived personalization profile (aggregate features, no raw PII)
 */
class Profile extends AbstractModel
{
    public const TYPE_ANONYMOUS = 'anonymous';
    public const TYPE_GUEST = 'guest';
    public const TYPE_CUSTOMER = 'customer';

    /**
     * @var string
     */
    protected $_eventPrefix = 'saurabhg_airecommend_profile';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Profile::class);
    }

    /**
     * @return array
     */
    public function getFeatures(): array
    {
        $decoded = json_decode((string)$this->getData('features_json'), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array $features
     * @return $this
     */
    public function setFeatures(array $features): self
    {
        return $this->setData('features_json', json_encode($features, JSON_UNESCAPED_UNICODE));
    }
}
