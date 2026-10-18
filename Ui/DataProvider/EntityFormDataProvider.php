<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Ui\DataProvider;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Saurabhg\AIproductRecommend\Model\Config;

/**
 * Generic edit form provider: CSV columns become multiselect arrays, JSON columns become dynamic rows,
 * UTC datetimes become admin-local dates. Configured per entity through virtual types in di.xml.
 */
class EntityFormDataProvider extends AbstractDataProvider
{
    private ?array $loadedData = null;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param AbstractCollection $collection non-shared collection of the entity
     * @param DataPersistorInterface $dataPersistor
     * @param TimezoneInterface $timezone
     * @param string $persistKey
     * @param string[] $csvFields
     * @param string[] $jsonFields
     * @param string[] $dateTimeFields
     * @param FormDataModifierInterface|null $modifier
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        AbstractCollection $collection,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly TimezoneInterface $timezone,
        private readonly string $persistKey = '',
        private readonly array $csvFields = [],
        private readonly array $jsonFields = [],
        private readonly array $dateTimeFields = [],
        private readonly ?FormDataModifierInterface $modifier = null,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collection;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * @inheritdoc
     */
    public function getData()
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }
        $this->loadedData = [];
        foreach ($this->collection->getItems() as $model) {
            $values = $model->getData();
            foreach ($this->csvFields as $field) {
                $values[$field] = Config::splitList((string)($values[$field] ?? ''));
            }
            foreach ($this->jsonFields as $field) {
                $rows = json_decode((string)($values[$field] ?? ''), true);
                $values[$field] = [];
                foreach (is_array($rows) ? array_values($rows) : [] as $index => $row) {
                    $values[$field][] = ['record_id' => $index] + (array)$row;
                }
            }
            foreach ($this->dateTimeFields as $field) {
                if (!empty($values[$field])) {
                    $values[$field] = $this->timezone->date(new \DateTime((string)$values[$field], new \DateTimeZone('UTC')))
                        ->format('Y-m-d');
                }
            }
            if ($this->modifier) {
                $values = $this->modifier->modify($values);
            }
            $this->loadedData[$model->getId()] = $values;
        }
        $persisted = $this->persistKey !== '' ? $this->dataPersistor->get($this->persistKey) : null;
        if (!empty($persisted)) {
            $id = $persisted[$this->primaryFieldName] ?? null;
            $this->loadedData[$id] = $persisted;
            $this->dataPersistor->clear($this->persistKey);
        }
        return $this->loadedData;
    }
}
