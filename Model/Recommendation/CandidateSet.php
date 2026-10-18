<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Recommendation;

/**
 * Candidate products with raw per-component scores and the reason code behind each component
 */
class CandidateSet
{
    /**
     * @var array<int, array<string, float>> product ID => component => score
     */
    private array $scores = [];

    /**
     * @var array<int, array<string, string>> product ID => component => reason code
     */
    private array $reasons = [];

    /**
     * Keeps the highest score per component (or sums it when $accumulate)
     *
     * @param int $productId
     * @param string $component behavior / similarity / popularity / trending / ai
     * @param float $score
     * @param string $reason
     * @param bool $accumulate
     * @return $this
     */
    public function add(int $productId, string $component, float $score, string $reason, bool $accumulate = false): self
    {
        if ($productId <= 0 || $score <= 0) {
            return $this;
        }
        $current = $this->scores[$productId][$component] ?? 0.0;
        if ($accumulate) {
            $this->scores[$productId][$component] = $current + $score;
            if ($score >= $current) {
                $this->reasons[$productId][$component] = $reason;
            }
        } elseif ($score > $current) {
            $this->scores[$productId][$component] = $score;
            $this->reasons[$productId][$component] = $reason;
        }
        return $this;
    }

    /**
     * @param array<int, float> $scores product ID => score
     * @param string $component
     * @param string $reason
     * @param float $factor
     * @param bool $accumulate
     * @return $this
     */
    public function addMany(
        array $scores,
        string $component,
        string $reason,
        float $factor = 1.0,
        bool $accumulate = false
    ): self {
        foreach ($scores as $productId => $score) {
            $this->add((int)$productId, $component, (float)$score * $factor, $reason, $accumulate);
        }
        return $this;
    }

    /**
     * Register a product with no score (pinned products)
     *
     * @param int $productId
     * @return $this
     */
    public function touch(int $productId): self
    {
        $this->scores[$productId] ??= [];
        return $this;
    }

    /**
     * @return int[]
     */
    public function getIds(): array
    {
        return array_keys($this->scores);
    }

    /**
     * @param int $productId
     * @return array<string, float>
     */
    public function getScores(int $productId): array
    {
        return $this->scores[$productId] ?? [];
    }

    /**
     * @param int $productId
     * @return array<string, string>
     */
    public function getReasons(int $productId): array
    {
        return $this->reasons[$productId] ?? [];
    }

    /**
     * @param int $productId
     * @return bool
     */
    public function has(int $productId): bool
    {
        return isset($this->scores[$productId]);
    }

    /**
     * @param int[] $productIds
     * @return $this
     */
    public function remove(array $productIds): self
    {
        foreach ($productIds as $productId) {
            unset($this->scores[(int)$productId], $this->reasons[(int)$productId]);
        }
        return $this;
    }

    /**
     * Keep only the given products
     *
     * @param int[] $productIds
     * @return $this
     */
    public function retain(array $productIds): self
    {
        $keep = array_flip(array_map('intval', $productIds));
        $this->scores = array_intersect_key($this->scores, $keep);
        $this->reasons = array_intersect_key($this->reasons, $keep);
        return $this;
    }

    /**
     * Scale a component to 0..1 by its maximum within the set
     *
     * @param string $component
     * @return $this
     */
    public function normalize(string $component): self
    {
        $max = 0.0;
        foreach ($this->scores as $components) {
            $max = max($max, $components[$component] ?? 0.0);
        }
        if ($max > 0) {
            foreach ($this->scores as $productId => $components) {
                if (isset($components[$component])) {
                    $this->scores[$productId][$component] = $components[$component] / $max;
                }
            }
        }
        return $this;
    }

    /**
     * Highest-scoring products of a component
     *
     * @param string $component
     * @param int $limit
     * @return int[]
     */
    public function top(string $component, int $limit): array
    {
        $values = [];
        foreach ($this->scores as $productId => $components) {
            if (isset($components[$component])) {
                $values[$productId] = $components[$component];
            }
        }
        arsort($values);
        return array_slice(array_keys($values), 0, $limit);
    }

    /**
     * @param CandidateSet $other
     * @return $this
     */
    public function merge(CandidateSet $other): self
    {
        foreach ($other->getIds() as $productId) {
            $reasons = $other->getReasons($productId);
            foreach ($other->getScores($productId) as $component => $score) {
                $this->add($productId, $component, $score, $reasons[$component] ?? 'popular');
            }
            $this->touch($productId);
        }
        return $this;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->scores);
    }
}
