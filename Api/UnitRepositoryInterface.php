<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Saurabhg\AIproductRecommend\Api\Data\UnitInterface;

/**
 * Recommendation unit persistence
 *
 * @api
 */
interface UnitRepositoryInterface
{
    /**
     * @param UnitInterface $unit
     * @return UnitInterface
     * @throws CouldNotSaveException
     */
    public function save(UnitInterface $unit): UnitInterface;

    /**
     * @param int $id
     * @return UnitInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $id): UnitInterface;

    /**
     * @param string $code
     * @return UnitInterface
     * @throws NoSuchEntityException
     */
    public function getByCode(string $code): UnitInterface;

    /**
     * @param UnitInterface $unit
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(UnitInterface $unit): bool;

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;
}
