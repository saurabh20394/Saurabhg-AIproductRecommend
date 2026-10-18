<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Recommendation;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Config\ConfigOptionsListConstants;

/**
 * Signed opaque token linking impression / click events to a recommendation response
 * without exposing internal identifiers and preventing tampering with experiment attribution
 */
class TrackingToken
{
    private const TTL = 172800;

    /**
     * @param DeploymentConfig $deploymentConfig
     */
    public function __construct(
        private readonly DeploymentConfig $deploymentConfig
    ) {
    }

    /**
     * @param string $unitCode
     * @param string $requestId
     * @param int $storeId
     * @param int|null $experimentId
     * @param int|null $variantId
     * @return string
     */
    public function encode(string $unitCode, string $requestId, int $storeId, ?int $experimentId, ?int $variantId): string
    {
        $payload = $this->base64(json_encode([
            'u' => $unitCode, 'r' => $requestId, 's' => $storeId,
            'e' => $experimentId, 'v' => $variantId, 't' => time(),
        ]));
        return $payload . '.' . $this->sign($payload);
    }

    /**
     * @param string|null $token
     * @return array{unit_code: string, request_id: string, store_id: int, experiment_id: ?int, variant_id: ?int}|null
     */
    public function decode(?string $token): ?array
    {
        if ($token === null || strlen($token) > 600 || !str_contains($token, '.')) {
            return null;
        }
        [$payload, $signature] = explode('.', $token, 2);
        if (!hash_equals($this->sign($payload), $signature)) {
            return null;
        }
        $data = json_decode((string)base64_decode(strtr($payload, '-_', '+/'), true), true);
        if (!is_array($data) || empty($data['u']) || (int)($data['t'] ?? 0) < time() - self::TTL) {
            return null;
        }
        return [
            'unit_code' => (string)$data['u'],
            'request_id' => (string)($data['r'] ?? ''),
            'store_id' => (int)($data['s'] ?? 0),
            'experiment_id' => isset($data['e']) ? (int)$data['e'] : null,
            'variant_id' => isset($data['v']) ? (int)$data['v'] : null,
        ];
    }

    /**
     * @param string $payload
     * @return string
     */
    private function sign(string $payload): string
    {
        $key = (string)$this->deploymentConfig->get(ConfigOptionsListConstants::CONFIG_PATH_CRYPT_KEY);
        return substr(hash_hmac('sha256', $payload, 'aipr|' . $key), 0, 24);
    }

    /**
     * @param string $value
     * @return string
     */
    private function base64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
