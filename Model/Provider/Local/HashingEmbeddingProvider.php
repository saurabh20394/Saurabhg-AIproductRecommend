<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Provider\Local;

use Saurabhg\AIproductRecommend\Api\EmbeddingProviderInterface;
use Saurabhg\AIproductRecommend\Model\Provider\VectorMath;

/**
 * Built-in, dependency-free embedding: signed feature hashing of weighted unigrams and bigrams.
 *
 * Captures lexical/attribute similarity (shared words, brand, categories). It is deterministic and runs
 * offline, which makes it the development / resilience fallback; use the HTTP provider for semantic models.
 */
class HashingEmbeddingProvider implements EmbeddingProviderInterface
{
    public const MODEL = 'local-hashing';
    public const VERSION = '1';

    private const STOPWORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'has', 'have', 'in', 'is', 'it', 'its',
        'of', 'on', 'or', 'our', 'that', 'the', 'this', 'to', 'was', 'with', 'you', 'your', 'will', 'can', 'all',
        'more', 'any', 'into', 'than', 'then', 'also', 'not', 'but', 'they', 'their', 'these', 'those', 'which',
    ];

    /**
     * @param int $dimensions
     */
    public function __construct(
        private readonly int $dimensions = 256
    ) {
    }

    /**
     * @inheritdoc
     */
    public function embed(array $texts, ?int $storeId = null): array
    {
        $result = [];
        foreach ($texts as $key => $text) {
            $result[$key] = $this->embedOne((string)$text);
        }
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getModel(?int $storeId = null): string
    {
        return self::MODEL . '-' . $this->dimensions;
    }

    /**
     * @inheritdoc
     */
    public function getModelVersion(?int $storeId = null): string
    {
        return self::VERSION;
    }

    /**
     * Text sections may be weighted with a "Label^weight:" prefix per line, e.g. "brand^3: Acme"
     *
     * @param string $text
     * @return float[]
     */
    public function embedOne(string $text): array
    {
        $vector = array_fill(0, $this->dimensions, 0.0);
        $frequencies = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $weight = 1.0;
            if (preg_match('/^\s*[a-z_]+\^(\d+(?:\.\d+)?):\s*(.*)$/iu', $line, $match)) {
                $weight = (float)$match[1];
                $line = $match[2];
            }
            $tokens = $this->tokenize($line);
            $previous = null;
            foreach ($tokens as $token) {
                $frequencies[$token] = ($frequencies[$token] ?? 0) + $weight;
                if ($previous !== null) {
                    $bigram = $previous . '_' . $token;
                    $frequencies[$bigram] = ($frequencies[$bigram] ?? 0) + $weight * 0.5;
                }
                $previous = $token;
            }
        }
        foreach ($frequencies as $token => $frequency) {
            $hash = crc32((string)$token);
            $index = $hash % $this->dimensions;
            $sign = (crc32($token . '#') & 1) ? 1.0 : -1.0;
            $vector[$index] += $sign * (1.0 + log(1.0 + $frequency));
        }
        return VectorMath::normalize($vector);
    }

    /**
     * @param string $text
     * @return string[]
     */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $text) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            if (mb_strlen($part) < 2 || in_array($part, self::STOPWORDS, true)) {
                continue;
            }
            // light stemming of plurals improves recall for catalog wording
            if (mb_strlen($part) > 4 && str_ends_with($part, 's') && !str_ends_with($part, 'ss')) {
                $part = mb_substr($part, 0, -1);
            }
            $tokens[] = $part;
        }
        return $tokens;
    }
}
