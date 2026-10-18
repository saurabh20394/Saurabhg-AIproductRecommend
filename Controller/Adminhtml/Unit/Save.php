<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Adminhtml\Unit;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Stdlib\DateTime\Filter\Date as DateFilter;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Saurabhg\AIproductRecommend\Controller\Adminhtml\AbstractSave;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Unit as EntityResource;
use Saurabhg\AIproductRecommend\Model\Source\FallbackType;
use Saurabhg\AIproductRecommend\Model\Source\Placement;
use Saurabhg\AIproductRecommend\Model\Source\UnitType;
use Saurabhg\AIproductRecommend\Model\UnitFactory;

/**
 * Save a recommendation unit
 */
class Save extends AbstractSave
{
    public const ADMIN_RESOURCE = 'Saurabhg_AIproductRecommend::units';
    protected const ENTITY_LABEL = 'Recommendation Unit';
    protected const PERSIST_KEY = 'aipr_unit';

    /**
     * @param Context $context
     * @param UnitFactory $modelFactory
     * @param EntityResource $resource
     * @param DataPersistorInterface $dataPersistor
     * @param DateFilter $dateFilter
     * @param TimezoneInterface $timezone
     * @param UnitType $unitType
     * @param Placement $placement
     * @param FallbackType $fallbackType
     */
    public function __construct(
        Context $context,
        UnitFactory $modelFactory,
        EntityResource $resource,
        DataPersistorInterface $dataPersistor,
        DateFilter $dateFilter,
        TimezoneInterface $timezone,
        private readonly UnitType $unitType,
        private readonly Placement $placement,
        private readonly FallbackType $fallbackType
    ) {
        parent::__construct($context, $modelFactory, $resource, $dataPersistor, $dateFilter, $timezone);
    }

    /**
     * @inheritdoc
     */
    protected function prepareData(array $data, AbstractModel $model): array
    {
        $code = trim((string)($data['code'] ?? ''));
        if (!preg_match('/^[a-z0-9_\-]{2,64}$/i', $code)) {
            throw new LocalizedException(__('Unit code must be 2-64 letters, digits, "_" or "-".'));
        }
        $existing = $this->modelFactory->create();
        $this->resource->load($existing, $code, 'code');
        if ($existing->getId() && (int)$existing->getId() !== (int)$model->getId()) {
            throw new LocalizedException(__('Unit code "%1" is already used.', $code));
        }
        $type = (string)($data['type'] ?? '');
        if (!array_key_exists($type, $this->unitType->getOptions())) {
            throw new LocalizedException(__('Select a valid recommendation type.'));
        }
        $placement = (string)($data['placement'] ?? Placement::WIDGET_ONLY);
        if (!array_key_exists($placement, $this->placement->getOptions())) {
            throw new LocalizedException(__('Select a valid placement.'));
        }
        $fallback = (string)($data['fallback_type'] ?? FallbackType::NONE);
        if (!array_key_exists($fallback, $this->fallbackType->getOptions())) {
            throw new LocalizedException(__('Select a valid fallback strategy.'));
        }
        $count = (int)($data['product_count'] ?? 6);
        if ($count < 1 || $count > 50) {
            throw new LocalizedException(__('Product count must be between 1 and 50.'));
        }
        $minScore = (float)($data['min_score'] ?? 0);
        if ($minScore < 0 || $minScore > 10) {
            throw new LocalizedException(__('Minimum score must be between 0 and 10.'));
        }
        return [
            'code' => $code,
            'title' => mb_substr(trim((string)($data['title'] ?? '')), 0, 255),
            'type' => $type,
            'status' => (int)!empty($data['status']),
            'store_ids' => $this->toCsv($data['store_ids'] ?? '0') ?? '0',
            'placement' => $placement,
            'position' => (int)($data['position'] ?? 0),
            'product_count' => $count,
            'min_score' => $minScore,
            'fallback_type' => $fallback,
            'customer_group_ids' => $this->toCsv($data['customer_group_ids'] ?? null),
            'segment_ids' => $this->toCsv($data['segment_ids'] ?? null),
            'show_reason' => (int)!empty($data['show_reason']),
            'pinned_skus' => trim((string)($data['pinned_skus'] ?? '')) ?: null,
            'excluded_skus' => trim((string)($data['excluded_skus'] ?? '')) ?: null,
            'excluded_category_ids' => $this->toCsv($data['excluded_category_ids'] ?? null),
            'complementary_map' => trim((string)($data['complementary_map'] ?? '')) ?: null,
        ];
    }
}
