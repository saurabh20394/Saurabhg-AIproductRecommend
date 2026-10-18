<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Adminhtml\Report;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Saurabhg\AIproductRecommend\ViewModel\Adminhtml\Dashboard;

/**
 * CSV export of the daily per-unit report
 */
class Export extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Saurabhg_AIproductRecommend::reports';

    private const COLUMNS = [
        'period', 'unit_code', 'impressions', 'clicks', 'ctr', 'add_to_carts', 'atc_rate',
        'orders', 'conversion_rate', 'revenue', 'aov',
    ];

    /**
     * @param Context $context
     * @param FileFactory $fileFactory
     * @param Dashboard $dashboard
     */
    public function __construct(
        Context $context,
        private readonly FileFactory $fileFactory,
        private readonly Dashboard $dashboard
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $range = $this->dashboard->getRange();
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, self::COLUMNS, ',', '"', '\\');
        foreach ($this->dashboard->getDailyByUnit() as $row) {
            $line = [];
            foreach (self::COLUMNS as $column) {
                $value = (string)($row[$column] ?? '');
                // neutralize spreadsheet formula injection
                $line[] = preg_match('/^[=+\-@]/', $value) && !is_numeric($value) ? "'" . $value : $value;
            }
            fputcsv($stream, $line, ',', '"', '\\');
        }
        rewind($stream);
        $content = (string)stream_get_contents($stream);
        fclose($stream);
        return $this->fileFactory->create(
            sprintf('ai-recommendations-%s-%s.csv', $range['from'], $range['to']),
            $content,
            DirectoryList::VAR_DIR,
            'text/csv'
        );
    }
}
