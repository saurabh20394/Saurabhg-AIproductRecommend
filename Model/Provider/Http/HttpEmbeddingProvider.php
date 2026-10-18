<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Provider\Http;

use Magento\Framework\Exception\LocalizedException;
use Saurabhg\AIproductRecommend\Api\EmbeddingProviderInterface;
use Saurabhg\AIproductRecommend\Model\Config;

/**
 * OpenAI-compatible embeddings API: POST {model, input[]} -> {data: [{index, embedding[]}], model}
 *
 * Works with OpenAI, Ollama (/v1/embeddings), vLLM, LiteLLM, Azure gateways and custom FastAPI services.
 */
class HttpEmbeddingProvider implements EmbeddingProviderInterface
{
    /**
     * @param Client $client
     * @param Config $config
     */
    public function __construct(
        private readonly Client $client,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritdoc
     */
    public function embed(array $texts, ?int $storeId = null): array
    {
        if (!$texts) {
            return [];
        }
        $keys = array_keys($texts);
        $input = array_map(static fn($text) => mb_substr((string)$text, 0, 8000), array_values($texts));
        $response = $this->client->postJson(
            $this->config->getServiceUrl('ai/embedding_path', $storeId),
            ['model' => $this->getModel($storeId), 'input' => $input],
            (int)$storeId,
            max(1, $this->config->getInt('ai/batch_timeout', $storeId, 30)) * 1000
        );
        $data = $response['data'] ?? $response['embeddings'] ?? null;
        if (!is_array($data) || count($data) !== count($input)) {
            throw new LocalizedException(__('Embedding response does not match the number of inputs.'));
        }
        $result = [];
        $dimensions = null;
        foreach ($data as $position => $row) {
            $vector = is_array($row) && isset($row['embedding']) ? $row['embedding'] : $row;
            $index = is_array($row) && isset($row['index']) ? (int)$row['index'] : (int)$position;
            if (!is_array($vector) || !$vector || !isset($keys[$index])) {
                throw new LocalizedException(__('Embedding response contains an invalid vector.'));
            }
            $vector = array_map('floatval', array_values($vector));
            $dimensions ??= count($vector);
            if (count($vector) !== $dimensions || $dimensions > 8192) {
                throw new LocalizedException(__('Embedding response has inconsistent dimensions.'));
            }
            $result[$keys[$index]] = $vector;
        }
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getModel(?int $storeId = null): string
    {
        return (string)($this->config->getValue('ai/embedding_model', $storeId) ?: 'text-embedding-3-small');
    }

    /**
     * @inheritdoc
     */
    public function getModelVersion(?int $storeId = null): string
    {
        return 'http-1';
    }
}
