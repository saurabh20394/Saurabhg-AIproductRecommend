<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Rule;

use Saurabhg\AIproductRecommend\Model\Config;

/**
 * Deterministic condition evaluation shared by business rules and segments
 */
class ConditionEvaluator
{
    /**
     * @param array $conditions [{field, operator, value}]
     * @param array $values field => scalar|array actual values
     * @param string $match all / any
     * @return bool true when there are no conditions
     */
    public function matchAll(array $conditions, array $values, string $match = 'all'): bool
    {
        $conditions = array_values(array_filter($conditions, static fn($c) => is_array($c) && !empty($c['field'])));
        if (!$conditions) {
            return true;
        }
        foreach ($conditions as $condition) {
            $result = $this->match(
                $values[$condition['field']] ?? null,
                (string)($condition['operator'] ?? 'eq'),
                (string)($condition['value'] ?? '')
            );
            if ($match === 'any' && $result) {
                return true;
            }
            if ($match !== 'any' && !$result) {
                return false;
            }
        }
        return $match !== 'any';
    }

    /**
     * Array actual values match when any element matches (negative operators: when none matches)
     *
     * @param mixed $actual
     * @param string $operator
     * @param string $expected
     * @return bool
     */
    public function match($actual, string $operator, string $expected): bool
    {
        $actualValues = is_array($actual) ? array_values($actual) : ($actual === null ? [] : [$actual]);
        $actualValues = array_map(static fn($v) => mb_strtolower(trim((string)$v)), $actualValues);
        $expectedList = array_map('mb_strtolower', Config::splitList($expected));
        $expectedOne = mb_strtolower(trim($expected));

        switch ($operator) {
            case 'eq':
                return in_array($expectedOne, $actualValues, true);
            case 'neq':
                return !in_array($expectedOne, $actualValues, true);
            case 'in':
                return (bool)array_intersect($actualValues, $expectedList);
            case 'nin':
                return !array_intersect($actualValues, $expectedList);
            case 'contains':
                foreach ($actualValues as $value) {
                    if ($expectedOne !== '' && str_contains($value, $expectedOne)) {
                        return true;
                    }
                }
                return false;
            case 'gt':
            case 'gte':
            case 'lt':
            case 'lte':
                if (!is_numeric($expectedOne)) {
                    return false;
                }
                foreach ($actualValues as $value) {
                    if (is_numeric($value) && $this->compare((float)$value, $operator, (float)$expectedOne)) {
                        return true;
                    }
                }
                return false;
            default:
                return false;
        }
    }

    /**
     * @param float $actual
     * @param string $operator
     * @param float $expected
     * @return bool
     */
    private function compare(float $actual, string $operator, float $expected): bool
    {
        return match ($operator) {
            'gt' => $actual > $expected,
            'gte' => $actual >= $expected,
            'lt' => $actual < $expected,
            default => $actual <= $expected,
        };
    }
}
