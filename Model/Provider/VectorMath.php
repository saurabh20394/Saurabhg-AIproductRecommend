<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Provider;

/**
 * Vector encoding and similarity helpers
 */
class VectorMath
{
    /**
     * Compact storage: base64 of little-endian float32
     *
     * @param float[] $vector
     * @return string
     */
    public static function pack(array $vector): string
    {
        return base64_encode(pack('g*', ...array_map('floatval', array_values($vector))));
    }

    /**
     * @param string|null $encoded
     * @return float[]
     */
    public static function unpack(?string $encoded): array
    {
        if ($encoded === null || $encoded === '') {
            return [];
        }
        $binary = base64_decode($encoded, true);
        if ($binary === false || strlen($binary) % 4 !== 0) {
            return [];
        }
        $values = unpack('g*', $binary);
        return $values ? array_values($values) : [];
    }

    /**
     * @param float[] $vector
     * @return float[]
     */
    public static function normalize(array $vector): array
    {
        $norm = 0.0;
        foreach ($vector as $value) {
            $norm += $value * $value;
        }
        if ($norm <= 0.0) {
            return $vector;
        }
        $norm = sqrt($norm);
        foreach ($vector as $i => $value) {
            $vector[$i] = $value / $norm;
        }
        return $vector;
    }

    /**
     * Cosine similarity mapped to 0..1 (vectors need not be normalized)
     *
     * @param float[] $a
     * @param float[] $b
     * @return float
     */
    public static function cosine(array $a, array $b): float
    {
        $count = min(count($a), count($b));
        if ($count === 0) {
            return 0.0;
        }
        $dot = $na = $nb = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }
        return max(0.0, min(1.0, $dot / (sqrt($na) * sqrt($nb))));
    }
}
