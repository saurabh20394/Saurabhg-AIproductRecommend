<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Event;

use Magento\Framework\HTTP\Header;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\MessageQueue\PublisherInterface;
use Saurabhg\AIproductRecommend\Logger\Logger;
use Saurabhg\AIproductRecommend\Model\Config;
use Saurabhg\AIproductRecommend\Model\Data\EventSubmitResult;
use Saurabhg\AIproductRecommend\Model\Data\EventSubmitResultFactory;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ErrorLog;

/**
 * Entry point for all events: validation, bot filtering, rate limiting, then asynchronous processing
 * through the message queue (inline processing when async is off or the queue is unavailable)
 */
class EventIngestion
{
    public const TOPIC = 'saurabhg.airecommend.event';

    private const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|headless|lighthouse|pingdom|monitor|curl|wget|python-requests/i';

    /**
     * @param EventValidator $validator
     * @param EventProcessor $processor
     * @param RateLimiter $rateLimiter
     * @param PublisherInterface $publisher
     * @param Config $config
     * @param Header $httpHeader
     * @param RemoteAddress $remoteAddress
     * @param EventSubmitResultFactory $resultFactory
     * @param ErrorLog $errorLog
     * @param Logger $logger
     */
    public function __construct(
        private readonly EventValidator $validator,
        private readonly EventProcessor $processor,
        private readonly RateLimiter $rateLimiter,
        private readonly PublisherInterface $publisher,
        private readonly Config $config,
        private readonly Header $httpHeader,
        private readonly RemoteAddress $remoteAddress,
        private readonly EventSubmitResultFactory $resultFactory,
        private readonly ErrorLog $errorLog,
        private readonly Logger $logger
    ) {
    }

    /**
     * @param array[] $rawEvents
     * @param int $storeId
     * @param int|null $customerId resolved by the server (session / token)
     * @param string|null $visitorId resolved by the server (cookie, or validated client value for headless)
     * @param string $source web / api / graphql / server
     * @param bool $trusted server-side events (no rate limit, purchase allowed)
     * @return EventSubmitResult
     */
    public function ingest(
        array $rawEvents,
        int $storeId,
        ?int $customerId,
        ?string $visitorId,
        string $source,
        bool $trusted = false
    ): EventSubmitResult {
        $total = count($rawEvents);
        if (!$total) {
            return $this->result(0, 0, []);
        }
        if (!$this->config->isTrackingEnabled($storeId)) {
            return $this->result(0, $total, ['Tracking is disabled for this store.']);
        }
        if (!$trusted) {
            if (preg_match(self::BOT_PATTERN, (string)$this->httpHeader->getHttpUserAgent())) {
                return $this->result(0, $total, ['Automated agents are not tracked.']);
            }
            $max = max(1, $this->config->getInt('tracking/max_batch', null, 50));
            if ($total > $max) {
                return $this->result(0, $total, [sprintf('At most %d events per request.', $max)]);
            }
            $key = ($visitorId ?? ('c' . $customerId)) . '|' . $this->remoteAddress->getRemoteAddress();
            if (!$this->rateLimiter->consume($key, $total, max(1, $this->config->getInt('tracking/rate_limit', null, 120)))) {
                return $this->result(0, $total, ['Rate limit exceeded.']);
            }
        }

        $rows = [];
        $errors = [];
        foreach ($rawEvents as $index => $raw) {
            try {
                $rows[] = $this->validator->normalize((array)$raw, $storeId, $customerId, $visitorId, $source, $trusted);
            } catch (\InvalidArgumentException $e) {
                $errors[] = sprintf('#%d: %s', $index, $e->getMessage());
            }
        }
        if ($rows) {
            $this->dispatch($rows, $storeId);
        }
        return $this->result(count($rows), $total - count($rows), array_slice($errors, 0, 20));
    }

    /**
     * @param array $rows
     * @param int $storeId
     * @return void
     */
    private function dispatch(array $rows, int $storeId): void
    {
        if ($this->config->isAsync($storeId)) {
            try {
                $this->publisher->publish(self::TOPIC, (string)json_encode($rows));
                return;
            } catch (\Throwable $e) {
                $this->logger->warning('Event queue unavailable, processing inline', ['error' => $e]);
            }
        }
        try {
            $this->processor->process($rows);
        } catch (\Throwable $e) {
            $this->logger->error('Event processing failed', ['error' => $e]);
            $this->errorLog->add(ErrorLog::JOB_EVENT, $e->getMessage(), $rows);
        }
    }

    /**
     * @param int $accepted
     * @param int $rejected
     * @param string[] $errors
     * @return EventSubmitResult
     */
    private function result(int $accepted, int $rejected, array $errors): EventSubmitResult
    {
        return $this->resultFactory->create(['data' => [
            'accepted' => $accepted, 'rejected' => $rejected, 'errors' => $errors,
        ]]);
    }
}
