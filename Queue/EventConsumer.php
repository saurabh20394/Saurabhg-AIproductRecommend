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
use Saurabhg\AIproductRecommend\Model\Event\EventProcessor;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ErrorLog;

/**
 * Consumer saurabhgAiRecommendEvent: persists batches of validated events.
 * Failed batches go to the error log (dead-letter) and can be retried from the admin monitor.
 */
class EventConsumer
{
    /**
     * @param EventProcessor $processor
     * @param ErrorLog $errorLog
     * @param Logger $logger
     */
    public function __construct(
        private readonly EventProcessor $processor,
        private readonly ErrorLog $errorLog,
        private readonly Logger $logger
    ) {
    }

    /**
     * @param string $message JSON array of normalized event rows
     * @return void
     */
    public function process(string $message): void
    {
        $rows = json_decode($message, true);
        if (!is_array($rows)) {
            $this->logger->warning('Discarded malformed event queue message');
            return;
        }
        try {
            $this->processor->process($rows);
        } catch (\Throwable $e) {
            $correlationId = bin2hex(random_bytes(8));
            $this->logger->error('Event batch failed', ['correlation_id' => $correlationId, 'error' => $e]);
            $this->errorLog->add(ErrorLog::JOB_EVENT, $e->getMessage(), $rows, $correlationId);
        }
    }
}
