<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Adminhtml\Experiment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Stdlib\DateTime\Filter\Date as DateFilter;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Saurabhg\AIproductRecommend\Controller\Adminhtml\AbstractSave;
use Saurabhg\AIproductRecommend\Model\ExperimentFactory;
use Saurabhg\AIproductRecommend\Model\Recommendation\Ranker;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Experiment as EntityResource;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Unit as UnitResource;
use Saurabhg\AIproductRecommend\Model\Source\ExperimentStatus;
use Saurabhg\AIproductRecommend\Model\Source\UnitType;
use Saurabhg\AIproductRecommend\Model\UnitFactory;

/**
 * Save an A/B experiment and its variants
 */
class Save extends AbstractSave
{
    public const ADMIN_RESOURCE = 'Saurabhg_AIproductRecommend::experiments';
    protected const ENTITY_LABEL = 'A/B Experiment';
    protected const PERSIST_KEY = 'aipr_experiment';

    /**
     * @var array
     */
    private array $variants = [];

    /**
     * @param Context $context
     * @param ExperimentFactory $modelFactory
     * @param EntityResource $resource
     * @param DataPersistorInterface $dataPersistor
     * @param DateFilter $dateFilter
     * @param TimezoneInterface $timezone
     * @param UnitFactory $unitFactory
     * @param UnitResource $unitResource
     * @param UnitType $unitType
     */
    public function __construct(
        Context $context,
        ExperimentFactory $modelFactory,
        EntityResource $resource,
        DataPersistorInterface $dataPersistor,
        DateFilter $dateFilter,
        TimezoneInterface $timezone,
        private readonly UnitFactory $unitFactory,
        private readonly UnitResource $unitResource,
        private readonly UnitType $unitType
    ) {
        parent::__construct($context, $modelFactory, $resource, $dataPersistor, $dateFilter, $timezone);
    }

    /**
     * @inheritdoc
     */
    protected function prepareData(array $data, AbstractModel $model): array
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new LocalizedException(__('Experiment name is required.'));
        }
        $unitCode = (string)($data['unit_code'] ?? '');
        $unit = $this->unitFactory->create();
        $this->unitResource->load($unit, $unitCode, 'code');
        if (!$unit->getId()) {
            throw new LocalizedException(__('Select an existing recommendation unit.'));
        }
        $status = (int)($data['status'] ?? ExperimentStatus::DRAFT);
        $this->variants = $this->prepareVariants($data['variants'] ?? []);
        $start = $this->toUtcDateTime($data['start_at'] ?? null, false);
        $end = $this->toUtcDateTime($data['end_at'] ?? null, true);
        if ($start && $end && $start > $end) {
            throw new LocalizedException(__('End date must be after the start date.'));
        }
        return [
            'name' => mb_substr($name, 0, 255),
            'unit_code' => $unitCode,
            'status' => in_array($status, [ExperimentStatus::DRAFT, ExperimentStatus::RUNNING, ExperimentStatus::STOPPED], true)
                ? $status : ExperimentStatus::DRAFT,
            'store_ids' => $this->toCsv($data['store_ids'] ?? '0') ?? '0',
            'start_at' => $start,
            'end_at' => $end,
            'description' => trim((string)($data['description'] ?? '')) ?: null,
        ];
    }

    /**
     * @inheritdoc
     */
    protected function afterSave(AbstractModel $model, array $data): void
    {
        /** @var EntityResource $resource */
        $resource = $this->resource;
        $resource->saveVariants((int)$model->getId(), $this->variants);
    }

    /**
     * Allocations must total 100 with exactly one control; overrides are validated JSON
     *
     * @param mixed $rows
     * @return array
     * @throws LocalizedException
     */
    private function prepareVariants($rows): array
    {
        $variants = [];
        $codes = [];
        $total = 0;
        $controls = 0;
        foreach ((array)$rows as $row) {
            if (!is_array($row) || !empty($row['delete'])) {
                continue;
            }
            $code = trim((string)($row['code'] ?? ''));
            if (!preg_match('/^[a-z0-9_\-]{1,32}$/i', $code) || isset($codes[$code])) {
                throw new LocalizedException(__('Variant codes must be unique (letters, digits, "_" or "-").'));
            }
            $codes[$code] = true;
            $allocation = (int)($row['allocation'] ?? 0);
            if ($allocation < 0 || $allocation > 100) {
                throw new LocalizedException(__('Allocation must be between 0 and 100.'));
            }
            $total += $allocation;
            $isControl = (int)!empty($row['is_control']);
            $controls += $isControl;
            $variants[] = [
                'variant_id' => (int)($row['variant_id'] ?? 0),
                'code' => $code,
                'name' => trim((string)($row['name'] ?? '')) ?: $code,
                'allocation' => $allocation,
                'is_control' => $isControl,
                'configuration' => $this->prepareConfiguration($row, $code),
            ];
        }
        if (count($variants) < 2) {
            throw new LocalizedException(__('An experiment needs a control and at least one variant.'));
        }
        if ($controls !== 1) {
            throw new LocalizedException(__('Mark exactly one variant as control.'));
        }
        if ($total !== 100) {
            throw new LocalizedException(__('Variant allocations must add up to 100 (currently %1).', $total));
        }
        return $variants;
    }

    /**
     * @param array $row
     * @param string $code
     * @return string|null
     * @throws LocalizedException
     */
    private function prepareConfiguration(array $row, string $code): ?string
    {
        $config = [];
        $type = (string)($row['type'] ?? '');
        if ($type !== '') {
            if ($type === 'holdout') {
                $config['disabled'] = true;
            } elseif (array_key_exists($type, $this->unitType->getOptions())) {
                $config['type'] = $type;
            } else {
                throw new LocalizedException(__('Variant "%1" has an invalid type.', $code));
            }
        }
        if (!empty($row['product_count'])) {
            $config['product_count'] = max(1, min(50, (int)$row['product_count']));
        }
        $weights = trim((string)($row['weights'] ?? ''));
        if ($weights !== '') {
            $decoded = json_decode($weights, true);
            if (!is_array($decoded)) {
                throw new LocalizedException(__('Variant "%1" weights must be a JSON object, e.g. {"similarity":0.6}.', $code));
            }
            foreach ($decoded as $feature => $weight) {
                if (!in_array($feature, Ranker::FEATURES, true) || !is_numeric($weight)) {
                    throw new LocalizedException(__('Variant "%1" has an unknown weight "%2".', $code, $feature));
                }
            }
            $config['weights'] = array_map('floatval', $decoded);
        }
        return $config ? (string)json_encode($config) : null;
    }
}
