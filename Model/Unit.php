<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Saurabhg\AIproductRecommend\Api\Data\UnitInterface;
use Saurabhg\AIproductRecommend\Model\Source\FallbackType;
use Saurabhg\AIproductRecommend\Model\Source\Placement;
use Saurabhg\AIproductRecommend\Model\Source\UnitType;

/**
 * Recommendation unit (widget) configuration
 */
class Unit extends AbstractEntity implements UnitInterface, IdentityInterface
{
    /**
     * Full page cache tag of pages rendering unit placeholders
     */
    public const CACHE_TAG_UNITS = 'aipr_units';

    /**
     * @var string
     */
    protected $_eventPrefix = 'saurabhg_airecommend_unit';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Unit::class);
    }

    /**
     * @inheritdoc
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG_UNITS];
    }

    /**
     * @inheritdoc
     */
    public function getCode(): string
    {
        return (string)$this->getData(self::CODE);
    }

    /**
     * @inheritdoc
     */
    public function setCode(string $code)
    {
        return $this->setData(self::CODE, $code);
    }

    /**
     * @inheritdoc
     */
    public function getTitle(): string
    {
        return (string)$this->getData(self::TITLE);
    }

    /**
     * @inheritdoc
     */
    public function setTitle(string $title)
    {
        return $this->setData(self::TITLE, $title);
    }

    /**
     * @inheritdoc
     */
    public function getType(): string
    {
        return (string)($this->getData(self::TYPE) ?: UnitType::POPULAR);
    }

    /**
     * @inheritdoc
     */
    public function setType(string $type)
    {
        return $this->setData(self::TYPE, $type);
    }

    /**
     * @inheritdoc
     */
    public function isActive(): bool
    {
        return (bool)(int)$this->getData(self::STATUS);
    }

    /**
     * @inheritdoc
     */
    public function getPlacement(): string
    {
        return (string)($this->getData(self::PLACEMENT) ?: Placement::WIDGET_ONLY);
    }

    /**
     * @inheritdoc
     */
    public function getProductCount(): int
    {
        return max(1, min(50, (int)($this->getData(self::PRODUCT_COUNT) ?: 6)));
    }

    /**
     * @inheritdoc
     */
    public function getMinScore(): float
    {
        return (float)$this->getData(self::MIN_SCORE);
    }

    /**
     * @inheritdoc
     */
    public function getFallbackType(): string
    {
        return (string)($this->getData(self::FALLBACK_TYPE) ?: FallbackType::NONE);
    }

    /**
     * @inheritdoc
     */
    public function getStoreIds(): array
    {
        return $this->getIntCsv(self::STORE_IDS);
    }

    /**
     * @inheritdoc
     */
    public function getCustomerGroupIds(): array
    {
        return $this->getIntCsv(self::CUSTOMER_GROUP_IDS);
    }

    /**
     * @inheritdoc
     */
    public function getSegmentIds(): array
    {
        return $this->getIntCsv(self::SEGMENT_IDS);
    }

    /**
     * @inheritdoc
     */
    public function isShowReason(): bool
    {
        return (bool)(int)$this->getData(self::SHOW_REASON);
    }

    /**
     * @inheritdoc
     */
    public function getPinnedSkus(): array
    {
        $pins = [];
        $slot = 1;
        foreach (preg_split('/[\r\n,]+/', (string)$this->getData(self::PINNED_SKUS)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode(':', $line, 2));
            $position = isset($parts[1]) && is_numeric($parts[1]) ? max(1, (int)$parts[1]) : $slot;
            $pins[$parts[0]] = $position;
            $slot = $position + 1;
        }
        return $pins;
    }

    /**
     * @inheritdoc
     */
    public function getExcludedSkus(): array
    {
        return $this->getCsv(self::EXCLUDED_SKUS);
    }

    /**
     * @inheritdoc
     */
    public function getExcludedCategoryIds(): array
    {
        return $this->getIntCsv(self::EXCLUDED_CATEGORY_IDS);
    }

    /**
     * @inheritdoc
     */
    public function getComplementaryMap(): array
    {
        $map = [];
        foreach (preg_split('/[\r\n]+/', (string)$this->getData(self::COMPLEMENTARY_MAP)) ?: [] as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$source, $targets] = array_map('trim', explode(':', $line, 2));
            $ids = array_map('intval', array_filter(Config::splitList($targets), 'is_numeric'));
            if ($source !== '' && $ids) {
                $map[$source] = array_values(array_unique(array_merge($map[$source] ?? [], $ids)));
            }
        }
        return $map;
    }

    /**
     * Whether the unit may be served in a store view to a customer group / set of segments
     *
     * @param int $storeId
     * @param int $customerGroupId
     * @param int[] $segmentIds
     * @return bool
     */
    public function isApplicable(int $storeId, int $customerGroupId, array $segmentIds): bool
    {
        if (!$this->isActive() || !$this->isForStore($storeId)) {
            return false;
        }
        $groups = $this->getCustomerGroupIds();
        if ($groups && !in_array($customerGroupId, $groups, true)) {
            return false;
        }
        $segments = $this->getSegmentIds();
        return !$segments || (bool)array_intersect($segments, $segmentIds);
    }
}
