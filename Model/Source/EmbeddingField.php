<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Source;

/**
 * Catalog content used to build embeddings
 */
class EmbeddingField extends AbstractOptions
{
    /**
     * @inheritdoc
     */
    public function getOptions(): array
    {
        return [
            'name' => __('Name'),
            'brand' => __('Brand'),
            'categories' => __('Category Names'),
            'short_description' => __('Short Description'),
            'description' => __('Description'),
            'meta_keyword' => __('Meta Keywords'),
        ];
    }
}
