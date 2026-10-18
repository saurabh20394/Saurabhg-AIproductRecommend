<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Provider\Http;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\CurlFactory;
use Saurabhg\AIproductRecommend\Logger\Logger;
use Saurabhg\AIproductRecommend\Model\Config;

/**
 * JSON-over-HTTP client for the AI service with strict timeouts and sanitized errors
 */
class Client
{
    /**
     * @param CurlFactory $curlFactory
     * @param Config $config
     */
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly Config $config
    ) {
    }

    /**
     * @param string $url
     * @param array $payload
     * @param int $storeId
     * @param int $timeoutMs
     * @return array decoded JSON object
     * @throws LocalizedException
     */
    public function postJson(string $url, array $payload, int $storeId, int $timeoutMs): array
    {
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            throw new LocalizedException(__('AI service URL is not configured.'));
        }
        $curl = $this->curlFactory->create();
        $curl->setOptions([
            CURLOPT_TIMEOUT_MS => max(50, $timeoutMs),
            CURLOPT_CONNECTTIMEOUT_MS => max(50, min($timeoutMs, 2000)),
            CURLOPT_NOSIGNAL => 1,
            CURLOPT_FOLLOWLOCATION => 0,
        ]);
        $curl->addHeader('Content-Type', 'application/json');
        $curl->addHeader('Accept', 'application/json');
        $apiKey = $this->config->getApiKey($storeId);
        if ($apiKey !== '') {
            $curl->addHeader('Authorization', 'Bearer ' . $apiKey);
        }
        try {
            $curl->post($url, (string)json_encode($payload, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            throw new LocalizedException(__('AI service request failed: %1', Logger::sanitize($e->getMessage())));
        }
        $status = $curl->getStatus();
        $body = (string)$curl->getBody();
        if ($status < 200 || $status >= 300) {
            throw new LocalizedException(
                __('AI service returned HTTP %1: %2', $status, Logger::sanitize(mb_substr($body, 0, 300)))
            );
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new LocalizedException(__('AI service returned an invalid JSON response.'));
        }
        return $decoded;
    }
}
