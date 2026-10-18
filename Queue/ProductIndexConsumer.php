<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Queue;

use Saurabhg\AIproductRecommend\Logger\Logger;
use Saurabhg\AIproductRecommend\Model\Catalog\ProductIndexer;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ErrorLog;

/**
 * Consumer saurabhgAiRecommendProductIndex: embeddings, vector neighbors and catalog sync of changed products
 */
class ProductIndexConsumer
{
    /**
     * @param ProductIndexer $indexer
     * @param ErrorLog $errorLog
     * @param Logger $logger
     */
    public function __construct(
        private readonly ProductIndexer $indexer,
        private readonly ErrorLog $errorLog,
        private readonly Logger $logger
    ) {
    }

    /**
     * @param string $message JSON {product_ids: int[], store_ids: int[]}
     * @return void
     */
    public function process(string $message): void
    {
        $data = json_decode($message, true);
        if (!is_array($data) || empty($data['product_ids'])) {
            return;
        }
        try {
            $this->indexer->reindex(
                (array)$data['product_ids'],
                !empty($data['store_ids']) ? array_map('intval', (array)$data['store_ids']) : null
            );
        } catch (\Throwable $e) {
            $this->logger->error('Product index job failed', ['error' => $e]);
            $this->errorLog->add(ErrorLog::JOB_PRODUCT_INDEX, $e->getMessage(), $data);
        }
    }
}
