<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Personalization;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Saurabhg\AIproductRecommend\Model\Config;

/**
 * Resolves the pseudonymous visitor and the logged-in customer of a storefront request,
 * honoring consent and tracking configuration
 */
class VisitorIdentity
{
    /**
     * @param CookieManagerInterface $cookieManager
     * @param CustomerSession $customerSession
     * @param ConsentChecker $consentChecker
     * @param Config $config
     */
    public function __construct(
        private readonly CookieManagerInterface $cookieManager,
        private readonly CustomerSession $customerSession,
        private readonly ConsentChecker $consentChecker,
        private readonly Config $config
    ) {
    }

    /**
     * @param string|null $visitorId
     * @return bool
     */
    public static function isValid(?string $visitorId): bool
    {
        return $visitorId !== null && (bool)preg_match('/^[A-Za-z0-9_\-]{16,64}$/', $visitorId);
    }

    /**
     * Visitor ID from the first-party cookie when anonymous tracking is permitted
     *
     * @param int $storeId
     * @return string|null
     */
    public function getVisitorId(int $storeId): ?string
    {
        if (!$this->config->isTrackingEnabled($storeId)
            || !$this->config->isSetFlag('tracking/anonymous', $storeId)
            || !$this->consentChecker->hasConsent($storeId)
        ) {
            return null;
        }
        $visitorId = $this->cookieManager->getCookie(Config::VISITOR_COOKIE);
        return self::isValid($visitorId) ? $visitorId : null;
    }

    /**
     * Customer ID when the customer is logged in (personalization flag and consent applied)
     *
     * @param int $storeId
     * @param bool $forPersonalization
     * @return int|null
     */
    public function getCustomerId(int $storeId, bool $forPersonalization = true): ?int
    {
        if (!$this->customerSession->isLoggedIn()) {
            return null;
        }
        if ($forPersonalization && (!$this->config->isSetFlag('tracking/personalize_customers', $storeId)
            || !$this->consentChecker->hasConsent($storeId))
        ) {
            return null;
        }
        return (int)$this->customerSession->getCustomerId() ?: null;
    }

    /**
     * Customer group of the current session (applies even without personalization consent)
     *
     * @return int
     */
    public function getCustomerGroupId(): int
    {
        return (int)$this->customerSession->getCustomerGroupId();
    }
}
