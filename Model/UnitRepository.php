<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Saurabhg\AIproductRecommend\Api\Data\UnitInterface;
use Saurabhg\AIproductRecommend\Api\UnitRepositoryInterface;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Unit as UnitResource;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Unit\CollectionFactory;

/**
 * Recommendation unit repository
 */
class UnitRepository implements UnitRepositoryInterface
{
    /**
     * @var array<string, UnitInterface>
     */
    private array $byCode = [];

    /**
     * @param UnitFactory $unitFactory
     * @param UnitResource $resource
     * @param CollectionFactory $collectionFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     */
    public function __construct(
        private readonly UnitFactory $unitFactory,
        private readonly UnitResource $resource,
        private readonly CollectionFactory $collectionFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function save(UnitInterface $unit): UnitInterface
    {
        try {
            /** @var Unit $unit */
            $this->resource->save($unit);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save the recommendation unit: %1', $e->getMessage()), $e);
        }
        $this->byCode = [];
        return $unit;
    }

    /**
     * @inheritdoc
     */
    public function getById(int $id): UnitInterface
    {
        $unit = $this->unitFactory->create();
        $this->resource->load($unit, $id);
        if (!$unit->getId()) {
            throw new NoSuchEntityException(__('Recommendation unit with ID "%1" does not exist.', $id));
        }
        return $unit;
    }

    /**
     * @inheritdoc
     */
    public function getByCode(string $code): UnitInterface
    {
        if (!isset($this->byCode[$code])) {
            $unit = $this->unitFactory->create();
            $this->resource->load($unit, $code, UnitInterface::CODE);
            if (!$unit->getId()) {
                throw new NoSuchEntityException(__('Recommendation unit "%1" does not exist.', $code));
            }
            $this->byCode[$code] = $unit;
        }
        return $this->byCode[$code];
    }

    /**
     * @inheritdoc
     */
    public function delete(UnitInterface $unit): bool
    {
        try {
            /** @var Unit $unit */
            $this->resource->delete($unit);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete the recommendation unit: %1', $e->getMessage()), $e);
        }
        $this->byCode = [];
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);
        $results = $this->searchResultsFactory->create();
        $results->setSearchCriteria($searchCriteria);
        $results->setItems($collection->getItems());
        $results->setTotalCount($collection->getSize());
        return $results;
    }
}
