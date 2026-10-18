<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed access to Stores > Configuration > Saurabh Extensions > AI Product Recommendations
 */
class Config
{
    public const SECTION = 'saurabhg_airecommend/';

    public const PROVIDER_LOCAL = 'local';
    public const PROVIDER_HTTP = 'http';

    public const CONSENT_NONE = 'none';
    public const CONSENT_MAGENTO = 'magento_cookie';
    public const CONSENT_CUSTOM = 'custom_cookie';

    public const VISITOR_COOKIE = 'aipr_vid';

    /**
     * Feature weights of the hybrid ranking formula (config key => feature)
     */
    public const WEIGHT_FEATURES = [
        'behavior', 'similarity', 'affinity', 'popularity', 'trending', 'conversion', 'inventory'
    ];

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    /**
     * Raw store-scoped value of a path relative to the module section
     *
     * @param string $path
     * @param int|string|null $storeId
     * @return mixed
     */
    public function getValue(string $path, $storeId = null)
    {
        return $this->scopeConfig->getValue(self::SECTION . $path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param string $path
     * @param int|string|null $storeId
     * @return bool
     */
    public function isSetFlag(string $path, $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::SECTION . $path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param string $path
     * @param int|string|null $storeId
     * @param int $default
     * @return int
     */
    public function getInt(string $path, $storeId = null, int $default = 0): int
    {
        $value = $this->getValue($path, $storeId);
        return is_numeric($value) ? (int)$value : $default;
    }

    /**
     * @param string $path
     * @param int|string|null $storeId
     * @param float $default
     * @return float
     */
    public function getFloat(string $path, $storeId = null, float $default = 0.0): float
    {
        $value = $this->getValue($path, $storeId);
        return is_numeric($value) ? (float)$value : $default;
    }

    /**
     * Comma / newline separated list
     *
     * @param string $path
     * @param int|string|null $storeId
     * @return string[]
     */
    public function getList(string $path, $storeId = null): array
    {
        return self::splitList((string)$this->getValue($path, $storeId));
    }

    /**
     * @param string $value
     * @return string[]
     */
    public static function splitList(string $value): array
    {
        $items = preg_split('/[\s,;]+/', $value) ?: [];
        return array_values(array_unique(array_filter(array_map('trim', $items), 'strlen')));
    }

    /**
     * @param int|string|null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null): bool
    {
        return $this->isSetFlag('general/enabled', $storeId);
    }

    /**
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->scopeConfig->isSetFlag(self::SECTION . 'general/debug_log');
    }

    /**
     * @param int|string|null $storeId
     * @return bool
     */
    public function isTrackingEnabled($storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->isSetFlag('tracking/enabled', $storeId);
    }

    /**
     * @param int|string|null $storeId
     * @return string
     */
    public function getConsentMode($storeId = null): string
    {
        return (string)($this->getValue('tracking/consent_mode', $storeId) ?: self::CONSENT_MAGENTO);
    }

    /**
     * @param int|string|null $storeId
     * @return bool
     */
    public function isAsync($storeId = null): bool
    {
        return $this->isSetFlag('tracking/async', $storeId);
    }

    /**
     * Ranking feature weights from configuration
     *
     * @param int|string|null $storeId
     * @return array<string, float>
     */
    public function getWeights($storeId = null): array
    {
        $weights = [];
        foreach (self::WEIGHT_FEATURES as $feature) {
            $weights[$feature] = $this->getFloat('ranking/w_' . $feature, $storeId);
        }
        return $weights;
    }

    /**
     * @param int|string|null $storeId
     * @return string
     */
    public function getEmbeddingProvider($storeId = null): string
    {
        return (string)($this->getValue('ai/embedding_provider', $storeId) ?: self::PROVIDER_LOCAL);
    }

    /**
     * @param int|string|null $storeId
     * @return string
     */
    public function getEndpoint($storeId = null): string
    {
        return rtrim(trim((string)$this->getValue('ai/endpoint', $storeId)), '/');
    }

    /**
     * Decrypted API key; values injected through env.php / environment variables are already plain text
     *
     * @param int|string|null $storeId
     * @return string
     */
    public function getApiKey($storeId = null): string
    {
        $value = trim((string)$this->getValue('ai/api_key', $storeId));
        if ($value === '') {
            return '';
        }
        $decrypted = $this->encryptor->decrypt($value);
        return $decrypted !== '' ? $decrypted : $value;
    }

    /**
     * @param string $pathKey ai/embedding_path, ai/ranking_path, ai/catalog_sync_path
     * @param int|string|null $storeId
     * @return string
     */
    public function getServiceUrl(string $pathKey, $storeId = null): string
    {
        $base = $this->getEndpoint($storeId);
        if ($base === '') {
            return '';
        }
        return $base . '/' . ltrim((string)$this->getValue($pathKey, $storeId), '/');
    }

    /**
     * @param int|string|null $storeId
     * @return bool
     */
    public function isRemoteRankingEnabled($storeId = null): bool
    {
        return $this->isSetFlag('ai/ranking_enabled', $storeId) && $this->getEndpoint($storeId) !== '';
    }

    /**
     * @param int|string|null $storeId
     * @return bool
     */
    public function isCatalogSyncEnabled($storeId = null): bool
    {
        return $this->isSetFlag('ai/catalog_sync_enabled', $storeId) && $this->getEndpoint($storeId) !== '';
    }

    /**
     * @param int|string|null $storeId
     * @return string
     */
    public function getBrandAttribute($storeId = null): string
    {
        return trim((string)$this->getValue('catalog/brand_attribute', $storeId));
    }
}
