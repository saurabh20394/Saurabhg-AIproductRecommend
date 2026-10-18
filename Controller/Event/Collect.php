<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Controller\Event;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Store\Model\StoreManagerInterface;
use Saurabhg\AIproductRecommend\Model\Event\EventIngestion;
use Saurabhg\AIproductRecommend\Model\Personalization\VisitorIdentity;

/**
 * Storefront event beacon endpoint. Identity and consent are resolved server-side from the session and
 * first-party cookie; the JSON body must carry the form key (CSRF protection compatible with sendBeacon).
 */
class Collect implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const MAX_BODY = 65536;

    /**
     * @var array|null
     */
    private ?array $body = null;

    /**
     * @param RequestInterface $request
     * @param JsonFactory $jsonFactory
     * @param FormKey $formKey
     * @param EventIngestion $ingestion
     * @param VisitorIdentity $visitorIdentity
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly FormKey $formKey,
        private readonly EventIngestion $ingestion,
        private readonly VisitorIdentity $visitorIdentity,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $body = $this->getBody();
        $storeId = (int)$this->storeManager->getStore()->getId();
        $events = array_values(array_filter((array)($body['events'] ?? []), 'is_array'));
        $result = $this->ingestion->ingest(
            $events,
            $storeId,
            $this->visitorIdentity->getCustomerId($storeId),
            $this->visitorIdentity->getVisitorId($storeId),
            'web'
        );
        return $this->jsonFactory->create()
            ->setHeader('Cache-Control', 'no-store', true)
            ->setData(['accepted' => $result->getAccepted(), 'rejected' => $result->getRejected()]);
    }

    /**
     * @inheritdoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        $key = (string)($this->getBody()['form_key'] ?? '');
        return $key !== '' && hash_equals((string)$this->formKey->getFormKey(), $key);
    }

    /**
     * @return array
     */
    private function getBody(): array
    {
        if ($this->body === null) {
            $content = method_exists($this->request, 'getContent') ? (string)$this->request->getContent() : '';
            $decoded = strlen($content) <= self::MAX_BODY ? json_decode($content, true) : null;
            $this->body = is_array($decoded) ? $decoded : [];
        }
        return $this->body;
    }
}
