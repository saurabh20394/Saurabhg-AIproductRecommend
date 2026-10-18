<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Personalization;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Saurabhg\AIproductRecommend\Model\Config;

/**
 * Server-side consent check mirroring the storefront tracker (no non-essential tracking without consent)
 */
class ConsentChecker
{
    private const MAGENTO_RESTRICTION_PATH = 'web/cookie/cookie_restriction';
    private const MAGENTO_ALLOW_COOKIE = 'user_allowed_save_cookie';

    /**
     * @param Config $config
     * @param ScopeConfigInterface $scopeConfig
     * @param CookieManagerInterface $cookieManager
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly Config $config,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CookieManagerInterface $cookieManager,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param int $storeId
     * @return bool
     */
    public function hasConsent(int $storeId): bool
    {
        switch ($this->config->getConsentMode($storeId)) {
            case Config::CONSENT_NONE:
                return true;
            case Config::CONSENT_CUSTOM:
                $name = trim((string)$this->config->getValue('tracking/consent_cookie', $storeId));
                $value = $name !== '' ? (string)$this->cookieManager->getCookie($name) : '';
                $required = trim((string)$this->config->getValue('tracking/consent_value', $storeId));
                return $value !== '' && ($required === '' || stripos(urldecode($value), $required) !== false);
            default:
                return $this->hasMagentoCookieConsent($storeId);
        }
    }

    /**
     * Client-side configuration for the tracker
     *
     * @param int $storeId
     * @return array
     */
    public function getClientConfig(int $storeId): array
    {
        $mode = $this->config->getConsentMode($storeId);
        if ($mode === Config::CONSENT_MAGENTO && !$this->isRestrictionMode($storeId)) {
            $mode = Config::CONSENT_NONE;
        }
        return [
            'mode' => $mode,
            'cookie' => $mode === Config::CONSENT_CUSTOM
                ? (string)$this->config->getValue('tracking/consent_cookie', $storeId)
                : self::MAGENTO_ALLOW_COOKIE,
            'value' => (string)$this->config->getValue('tracking/consent_value', $storeId),
            'websiteId' => (int)$this->storeManager->getStore($storeId)->getWebsiteId(),
        ];
    }

    /**
     * @param int $storeId
     * @return bool
     */
    private function hasMagentoCookieConsent(int $storeId): bool
    {
        if (!$this->isRestrictionMode($storeId)) {
            return true;
        }
        $allowed = json_decode((string)$this->cookieManager->getCookie(self::MAGENTO_ALLOW_COOKIE), true);
        $websiteId = (int)$this->storeManager->getStore($storeId)->getWebsiteId();
        return is_array($allowed) && !empty($allowed[$websiteId]);
    }

    /**
     * @param int $storeId
     * @return bool
     */
    private function isRestrictionMode(int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(self::MAGENTO_RESTRICTION_PATH, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
