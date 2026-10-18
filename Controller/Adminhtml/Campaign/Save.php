<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Adminhtml\Campaign;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Stdlib\DateTime\Filter\Date as DateFilter;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Saurabhg\AIproductRecommend\Controller\Adminhtml\AbstractSave;
use Saurabhg\AIproductRecommend\Model\CampaignFactory;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Campaign as EntityResource;

/**
 * Save a campaign
 */
class Save extends AbstractSave
{
    public const ADMIN_RESOURCE = 'Saurabhg_AIproductRecommend::campaigns';
    protected const ENTITY_LABEL = 'Campaign';
    protected const PERSIST_KEY = 'aipr_campaign';

    /**
     * @param Context $context
     * @param CampaignFactory $modelFactory
     * @param EntityResource $resource
     * @param DataPersistorInterface $dataPersistor
     * @param DateFilter $dateFilter
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        Context $context,
        CampaignFactory $modelFactory,
        EntityResource $resource,
        DataPersistorInterface $dataPersistor,
        DateFilter $dateFilter,
        TimezoneInterface $timezone
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
            throw new LocalizedException(__('Campaign name is required.'));
        }
        $amount = $data['boost_amount'] ?? 0.2;
        if (!is_numeric($amount) || abs((float)$amount) > 10) {
            throw new LocalizedException(__('Boost amount must be a number between -10 and 10.'));
        }
        $start = $this->toUtcDateTime($data['start_at'] ?? null, false);
        $end = $this->toUtcDateTime($data['end_at'] ?? null, true);
        if ($start && $end && $start > $end) {
            throw new LocalizedException(__('End date must be after the start date.'));
        }
        if (trim((string)($data['boost_skus'] ?? '')) === '' && !$this->toCsv($data['boost_category_ids'] ?? null)) {
            throw new LocalizedException(__('Enter boosted SKUs or category IDs.'));
        }
        return [
            'name' => mb_substr($name, 0, 255),
            'status' => (int)!empty($data['status']),
            'store_ids' => $this->toCsv($data['store_ids'] ?? '0') ?? '0',
            'unit_codes' => $this->toCsv($data['unit_codes'] ?? null),
            'segment_ids' => $this->toCsv($data['segment_ids'] ?? null),
            'start_at' => $start,
            'end_at' => $end,
            'boost_skus' => trim((string)($data['boost_skus'] ?? '')) ?: null,
            'boost_category_ids' => $this->toCsv($data['boost_category_ids'] ?? null),
            'boost_amount' => (float)$amount,
        ];
    }
}
