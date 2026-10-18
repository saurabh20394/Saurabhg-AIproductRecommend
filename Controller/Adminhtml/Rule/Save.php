<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Adminhtml\Rule;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Stdlib\DateTime\Filter\Date as DateFilter;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Saurabhg\AIproductRecommend\Controller\Adminhtml\AbstractSave;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Rule as EntityResource;
use Saurabhg\AIproductRecommend\Model\RuleFactory;
use Saurabhg\AIproductRecommend\Model\Source\Operator;
use Saurabhg\AIproductRecommend\Model\Source\RuleActionType;
use Saurabhg\AIproductRecommend\Model\Source\RuleConditionField;

/**
 * Save a business rule (conditions and actions are validated against the supported vocabulary)
 */
class Save extends AbstractSave
{
    public const ADMIN_RESOURCE = 'Saurabhg_AIproductRecommend::rules';
    protected const ENTITY_LABEL = 'Business Rule';
    protected const PERSIST_KEY = 'aipr_rule';

    /**
     * @param Context $context
     * @param RuleFactory $modelFactory
     * @param EntityResource $resource
     * @param DataPersistorInterface $dataPersistor
     * @param DateFilter $dateFilter
     * @param TimezoneInterface $timezone
     * @param RuleConditionField $conditionField
     * @param Operator $operator
     * @param RuleActionType $actionType
     */
    public function __construct(
        Context $context,
        RuleFactory $modelFactory,
        EntityResource $resource,
        DataPersistorInterface $dataPersistor,
        DateFilter $dateFilter,
        TimezoneInterface $timezone,
        private readonly RuleConditionField $conditionField,
        private readonly Operator $operator,
        private readonly RuleActionType $actionType
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
            throw new LocalizedException(__('Rule name is required.'));
        }
        $conditions = $this->cleanRows($data['conditions'] ?? [], ['field', 'operator', 'value']);
        foreach ($conditions as $condition) {
            if (!array_key_exists($condition['field'], $this->conditionField->getOptions())
                || !array_key_exists($condition['operator'], $this->operator->getOptions())
            ) {
                throw new LocalizedException(__('Every condition needs a valid field and operator.'));
            }
        }
        $actions = $this->cleanRows($data['actions'] ?? [], ['type', 'value', 'amount']);
        if (!$actions) {
            throw new LocalizedException(__('Add at least one action.'));
        }
        foreach ($actions as $index => $action) {
            if (!array_key_exists($action['type'], $this->actionType->getOptions())) {
                throw new LocalizedException(__('Action #%1 has an invalid type.', $index + 1));
            }
            if ($action['amount'] !== '' && !is_numeric($action['amount'])) {
                throw new LocalizedException(__('Action #%1 amount must be numeric.', $index + 1));
            }
            if ($action['type'] === 'boost_attribute' && !preg_match('/^[a-z][a-z0-9_]{0,59}$/', $action['value'])) {
                throw new LocalizedException(__('Action #%1 needs a valid attribute code.', $index + 1));
            }
        }
        $from = $this->toDate($data['from_date'] ?? null);
        $to = $this->toDate($data['to_date'] ?? null);
        if ($from && $to && $from > $to) {
            throw new LocalizedException(__('"To" date must be after "From" date.'));
        }
        return [
            'name' => mb_substr($name, 0, 255),
            'status' => (int)!empty($data['status']),
            'priority' => (int)($data['priority'] ?? 0),
            'stop_further' => (int)!empty($data['stop_further']),
            'store_ids' => $this->toCsv($data['store_ids'] ?? '0') ?? '0',
            'customer_group_ids' => $this->toCsv($data['customer_group_ids'] ?? null),
            'unit_codes' => $this->toCsv($data['unit_codes'] ?? null),
            'from_date' => $from,
            'to_date' => $to,
            'conditions' => json_encode($conditions),
            'actions' => json_encode($actions),
        ];
    }
}
