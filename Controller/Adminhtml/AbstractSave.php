<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Stdlib\DateTime\Filter\Date as DateFilter;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Saurabhg\AIproductRecommend\Model\Config;

/**
 * Validated save of a merchant entity; invalid input is kept in the data persistor and the form re-opened
 */
abstract class AbstractSave extends Action implements HttpPostActionInterface
{
    /**
     * Generated factory of the entity (model or collection)
     *
     * @var mixed
     */
    protected $modelFactory;

    protected const ENTITY_LABEL = 'Item';
    protected const PERSIST_KEY = 'aipr_entity';

    /**
     * @param Context $context
     * @param mixed $modelFactory
     * @param AbstractDb $resource
     * @param DataPersistorInterface $dataPersistor
     * @param DateFilter $dateFilter
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        Context $context,
        $modelFactory,
        protected readonly AbstractDb $resource,
        protected readonly DataPersistorInterface $dataPersistor,
        protected readonly DateFilter $dateFilter,
        protected readonly TimezoneInterface $timezone
    ) {
        parent::__construct($context);
        $this->modelFactory = $modelFactory;
    }

    /**
     * Validate and convert posted form data to column values
     *
     * @param array $data
     * @param AbstractModel $model
     * @return array
     * @throws LocalizedException
     */
    abstract protected function prepareData(array $data, AbstractModel $model): array;

    /**
     * Hook after a successful save
     *
     * @param AbstractModel $model
     * @param array $data posted data
     * @return void
     */
    protected function afterSave(AbstractModel $model, array $data): void
    {
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $data = (array)$this->getRequest()->getPostValue();
        $redirect = $this->resultRedirectFactory->create();
        if (!$data) {
            return $redirect->setPath('*/*/');
        }
        $id = (int)($data[$this->resource->getIdFieldName()] ?? 0);
        /** @var AbstractModel $model */
        $model = $this->modelFactory->create();
        if ($id) {
            $this->resource->load($model, $id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This %1 no longer exists.', __(static::ENTITY_LABEL)));
                return $redirect->setPath('*/*/');
            }
        }
        try {
            $values = $this->prepareData($data, $model);
            unset($values[$this->resource->getIdFieldName()]);
            $model->addData($values);
            $this->resource->save($model);
            $this->afterSave($model, $data);
            $this->dataPersistor->clear(static::PERSIST_KEY);
            $this->messageManager->addSuccessMessage(__('The %1 has been saved.', __(static::ENTITY_LABEL)));
            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['id' => $model->getId()]);
            }
            return $redirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the %1.', __(static::ENTITY_LABEL)));
        }
        $this->dataPersistor->set(static::PERSIST_KEY, $data);
        return $redirect->setPath('*/*/edit', $id ? ['id' => $id] : []);
    }

    /**
     * @param mixed $value posted multiselect value
     * @return string|null
     */
    protected function toCsv($value): ?string
    {
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        $items = Config::splitList((string)$value);
        return $items ? implode(',', $items) : null;
    }

    /**
     * Admin-local date to Y-m-d
     *
     * @param mixed $value
     * @return string|null
     * @throws LocalizedException
     */
    protected function toDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return substr((string)$this->dateFilter->filter((string)$value), 0, 10);
        } catch (\Exception $e) {
            throw new LocalizedException(__('Invalid date "%1".', $value));
        }
    }

    /**
     * Admin-local date to UTC datetime at the start or end of that day
     *
     * @param mixed $value
     * @param bool $endOfDay
     * @return string|null
     * @throws LocalizedException
     */
    protected function toUtcDateTime($value, bool $endOfDay): ?string
    {
        $date = $this->toDate($value);
        if ($date === null) {
            return null;
        }
        $local = new \DateTime($date . ($endOfDay ? ' 23:59:59' : ' 00:00:00'), new \DateTimeZone($this->timezone->getConfigTimezone()));
        return $local->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /**
     * dynamicRows records to clean rows
     *
     * @param mixed $rows
     * @param string[] $keys
     * @return array
     */
    protected function cleanRows($rows, array $keys): array
    {
        $clean = [];
        foreach ((array)$rows as $row) {
            if (!is_array($row) || !empty($row['delete'])) {
                continue;
            }
            $item = [];
            foreach ($keys as $key) {
                $item[$key] = trim((string)($row[$key] ?? ''));
            }
            $clean[] = $item;
        }
        return $clean;
    }
}
