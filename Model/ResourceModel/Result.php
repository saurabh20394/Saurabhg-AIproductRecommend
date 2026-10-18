<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Persistent last-good results used when live generation and the cache are unavailable
 */
class Result extends AbstractDb
{
    public const TABLE = 'ai_recommendation_result';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, 'result_id');
    }

    /**
     * @param string $hash
     * @param string|null $unitCode
     * @param int $storeId
     * @param array $payload
     * @param int[] $productIds
     * @param string $source
     * @param string|null $modelVersion
     * @param int $ttlHours
     * @return void
     */
    public function saveResult(
        string $hash,
        ?string $unitCode,
        int $storeId,
        array $payload,
        array $productIds,
        string $source,
        ?string $modelVersion,
        int $ttlHours
    ): void {
        $this->getConnection()->insertOnDuplicate($this->getMainTable(), [[
            'context_hash' => $hash,
            'unit_code' => $unitCode,
            'store_id' => $storeId,
            'product_ids' => implode(',', $productIds),
            'payload' => json_encode($payload),
            'source' => mb_substr($source, 0, 32),
            'model_version' => $modelVersion !== null ? mb_substr($modelVersion, 0, 64) : null,
            'generated_at' => gmdate('Y-m-d H:i:s'),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + max(1, $ttlHours) * 3600),
        ]], ['unit_code', 'store_id', 'product_ids', 'payload', 'source', 'model_version', 'generated_at', 'expires_at']);
    }

    /**
     * @param string $hash
     * @return array|null decoded payload
     */
    public function loadResult(string $hash): ?array
    {
        $connection = $this->getConnection();
        $payload = $connection->fetchOne(
            $connection->select()->from($this->getMainTable(), ['payload'])
                ->where('context_hash = ?', $hash)
                ->where('expires_at > ?', gmdate('Y-m-d H:i:s'))
        );
        $decoded = $payload ? json_decode((string)$payload, true) : null;
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param int[] $productIds
     * @return void
     */
    public function deleteContaining(array $productIds): void
    {
        $connection = $this->getConnection();
        foreach ($productIds as $productId) {
            $connection->delete(
                $this->getMainTable(),
                [new \Zend_Db_Expr($connection->quoteInto('FIND_IN_SET(?, product_ids)', (string)(int)$productId))]
            );
        }
    }

    /**
     * @return int
     */
    public function purgeExpired(): int
    {
        return $this->getConnection()->delete($this->getMainTable(), ['expires_at < ?' => gmdate('Y-m-d H:i:s')]);
    }

    /**
     * Drop all stored results (merchant configuration changed)
     *
     * @return void
     */
    public function clear(): void
    {
        $this->getConnection()->delete($this->getMainTable());
    }
}
