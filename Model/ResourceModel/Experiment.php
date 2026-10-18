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
 * ai_ab_experiment resource with variants and persisted assignments
 */
class Experiment extends AbstractDb
{
    public const TABLE = 'ai_ab_experiment';
    public const ID_FIELD = 'experiment_id';
    public const VARIANT_TABLE = 'ai_ab_variant';
    public const ASSIGNMENT_TABLE = 'ai_ab_assignment';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, self::ID_FIELD);
    }

    /**
     * @param int $experimentId
     * @return array<int, array> variant ID => row, control first
     */
    public function getVariants(int $experimentId): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()->from($this->getTable(self::VARIANT_TABLE))
            ->where('experiment_id = ?', $experimentId)
            ->order('is_control DESC')
            ->order('variant_id ASC');
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int)$row['variant_id']] = $row;
        }
        return $result;
    }

    /**
     * Synchronize variants with the submitted rows; existing variant IDs are preserved (assignments survive)
     *
     * @param int $experimentId
     * @param array $rows [variant_id?, code, name, allocation, is_control, configuration]
     * @return void
     */
    public function saveVariants(int $experimentId, array $rows): void
    {
        $connection = $this->getConnection();
        $table = $this->getTable(self::VARIANT_TABLE);
        $existing = $this->getVariants($experimentId);
        $existingByCode = [];
        foreach ($existing as $variantId => $row) {
            $existingByCode[$row['code']] = $variantId;
        }
        $kept = [];
        foreach ($rows as $row) {
            $code = (string)$row['code'];
            $data = [
                'experiment_id' => $experimentId,
                'code' => $code,
                'name' => $row['name'] ?? $code,
                'allocation' => (int)$row['allocation'],
                'is_control' => (int)$row['is_control'],
                'configuration' => $row['configuration'] ?? null,
            ];
            $variantId = (int)($row['variant_id'] ?? 0) ?: ($existingByCode[$code] ?? 0);
            if ($variantId && isset($existing[$variantId])) {
                $connection->update($table, $data, ['variant_id = ?' => $variantId]);
                $kept[] = $variantId;
            } else {
                $connection->insert($table, $data);
                $kept[] = (int)$connection->lastInsertId($table);
            }
        }
        $remove = array_diff(array_keys($existing), $kept);
        if ($remove) {
            $connection->delete($table, ['variant_id IN (?)' => $remove]);
        }
    }

    /**
     * @param int $experimentId
     * @param string $subjectKey
     * @return int|null
     */
    public function getAssignment(int $experimentId, string $subjectKey): ?int
    {
        $connection = $this->getConnection();
        $variantId = $connection->fetchOne(
            $connection->select()->from($this->getTable(self::ASSIGNMENT_TABLE), ['variant_id'])
                ->where('experiment_id = ?', $experimentId)
                ->where('subject_key = ?', $subjectKey)
        );
        return $variantId !== false ? (int)$variantId : null;
    }

    /**
     * First write wins: concurrent requests keep the same variant
     *
     * @param int $experimentId
     * @param int $variantId
     * @param string $subjectKey
     * @return int assigned variant ID
     */
    public function assign(int $experimentId, int $variantId, string $subjectKey): int
    {
        $connection = $this->getConnection();
        $connection->insertArray(
            $this->getTable(self::ASSIGNMENT_TABLE),
            ['experiment_id', 'variant_id', 'subject_key'],
            [[$experimentId, $variantId, $subjectKey]],
            \Magento\Framework\DB\Adapter\AdapterInterface::INSERT_IGNORE
        );
        return $this->getAssignment($experimentId, $subjectKey) ?? $variantId;
    }

    /**
     * @param int $experimentId
     * @return array<int, int> variant ID => assigned subjects
     */
    public function getAssignmentCounts(int $experimentId): array
    {
        $connection = $this->getConnection();
        return array_map('intval', $connection->fetchPairs(
            $connection->select()->from($this->getTable(self::ASSIGNMENT_TABLE), ['variant_id', new \Zend_Db_Expr('COUNT(*)')])
                ->where('experiment_id = ?', $experimentId)
                ->group('variant_id')
        ));
    }

    /**
     * @param string[] $subjectKeys
     * @return void
     */
    public function deleteAssignments(array $subjectKeys): void
    {
        if ($subjectKeys) {
            $this->getConnection()->delete($this->getTable(self::ASSIGNMENT_TABLE), ['subject_key IN (?)' => $subjectKeys]);
        }
    }
}
