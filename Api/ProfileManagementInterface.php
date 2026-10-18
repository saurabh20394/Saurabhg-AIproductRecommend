<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Api;

use Saurabhg\AIproductRecommend\Api\Data\ProfileInterface;

/**
 * Authorized access to recommendation profiles and privacy erasure
 *
 * @api
 */
interface ProfileManagementInterface
{
    /**
     * @param int $id customer ID
     * @return \Saurabhg\AIproductRecommend\Api\Data\ProfileInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getProfile(int $id): ProfileInterface;

    /**
     * Delete the profile and anonymize behavioral data of a customer
     *
     * @param int $id customer ID
     * @return bool
     */
    public function deleteProfile(int $id): bool;
}
