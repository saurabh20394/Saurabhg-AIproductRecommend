<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Provider;

use Saurabhg\AIproductRecommend\Api\EmbeddingProviderInterface;
use Saurabhg\AIproductRecommend\Api\RankingProviderInterface;
use Saurabhg\AIproductRecommend\Model\Config;

/**
 * Resolves the configured providers; new providers are registered through di.xml
 */
class ProviderPool
{
    /**
     * @param Config $config
     * @param EmbeddingProviderInterface[] $embeddingProviders code => provider
     * @param RankingProviderInterface $rankingProvider
     */
    public function __construct(
        private readonly Config $config,
        private readonly array $embeddingProviders,
        private readonly RankingProviderInterface $rankingProvider
    ) {
    }

    /**
     * @param int|null $storeId
     * @return EmbeddingProviderInterface
     */
    public function getEmbeddingProvider(?int $storeId = null): EmbeddingProviderInterface
    {
        $code = $this->config->getEmbeddingProvider($storeId);
        if ($code !== Config::PROVIDER_LOCAL && $this->config->getEndpoint($storeId) === '') {
            $code = Config::PROVIDER_LOCAL;
        }
        return $this->embeddingProviders[$code] ?? $this->embeddingProviders[Config::PROVIDER_LOCAL];
    }

    /**
     * Local provider used for on-the-fly cold-start similarity
     *
     * @return EmbeddingProviderInterface
     */
    public function getLocalEmbeddingProvider(): EmbeddingProviderInterface
    {
        return $this->embeddingProviders[Config::PROVIDER_LOCAL];
    }

    /**
     * @return RankingProviderInterface
     */
    public function getRankingProvider(): RankingProviderInterface
    {
        return $this->rankingProvider;
    }
}
