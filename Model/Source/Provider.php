<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Source;

use Saurabhg\AIproductRecommend\Model\Config;

/**
 * Embedding providers
 */
class Provider extends AbstractOptions
{
    /**
     * @inheritdoc
     */
    public function getOptions(): array
    {
        return [
            Config::PROVIDER_LOCAL => __('Local (built-in, no external service)'),
            Config::PROVIDER_HTTP => __('HTTP (OpenAI-compatible embeddings API)'),
        ];
    }
}
