<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Adminhtml\Segment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Stdlib\DateTime\Filter\Date as DateFilter;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Saurabhg\AIproductRecommend\Controller\Adminhtml\AbstractSave;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Segment as EntityResource;
use Saurabhg\AIproductRecommend\Model\SegmentFactory;
use Saurabhg\AIproductRecommend\Model\Source\Operator;
use Saurabhg\AIproductRecommend\Model\Source\SegmentConditionField;

/**
 * Save a customer segment
 */
class Save extends AbstractSave
{
    public const ADMIN_RESOURCE = 'Saurabhg_AIproductRecommend::segments';
    protected const ENTITY_LABEL = 'Customer Segment';
    protected const PERSIST_KEY = 'aipr_segment';

    /**
     * @param Context $context
     * @param SegmentFactory $modelFactory
     * @param EntityResource $resource
     * @param DataPersistorInterface $dataPersistor
     * @param DateFilter $dateFilter
     * @param TimezoneInterface $timezone
     * @param SegmentConditionField $conditionField
     * @param Operator $operator
     */
    public function __construct(
        Context $context,
        SegmentFactory $modelFactory,
        EntityResource $resource,
        DataPersistorInterface $dataPersistor,
        DateFilter $dateFilter,
        TimezoneInterface $timezone,
        private readonly SegmentConditionField $conditionField,
        private readonly Operator $operator
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
            throw new LocalizedException(__('Segment name is required.'));
        }
        $conditions = $this->cleanRows($data['conditions'] ?? [], ['field', 'operator', 'value']);
        if (!$conditions) {
            throw new LocalizedException(__('Add at least one condition.'));
        }
        foreach ($conditions as $condition) {
            if (!array_key_exists($condition['field'], $this->conditionField->getOptions())
                || !array_key_exists($condition['operator'], $this->operator->getOptions())
            ) {
                throw new LocalizedException(__('Every condition needs a valid field and operator.'));
            }
        }
        return [
            'name' => mb_substr($name, 0, 255),
            'status' => (int)!empty($data['status']),
            'match_type' => ($data['match_type'] ?? 'all') === 'any' ? 'any' : 'all',
            'description' => trim((string)($data['description'] ?? '')) ?: null,
            'conditions' => json_encode($conditions),
        ];
    }
}
