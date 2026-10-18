<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Ui\DataProvider;

use Saurabhg\AIproductRecommend\Model\ResourceModel\Experiment as ExperimentResource;

/**
 * Loads experiment variants into the "variants" dynamic rows
 */
class ExperimentVariantsModifier implements FormDataModifierInterface
{
    /**
     * @param ExperimentResource $resource
     */
    public function __construct(
        private readonly ExperimentResource $resource
    ) {
    }

    /**
     * @inheritdoc
     */
    public function modify(array $values): array
    {
        $values['variants'] = [];
        foreach ($this->resource->getVariants((int)($values['experiment_id'] ?? 0)) as $variant) {
            $config = json_decode((string)$variant['configuration'], true) ?: [];
            $values['variants'][] = [
                'record_id' => count($values['variants']),
                'variant_id' => (int)$variant['variant_id'],
                'code' => $variant['code'],
                'name' => $variant['name'],
                'allocation' => (int)$variant['allocation'],
                'is_control' => (string)(int)$variant['is_control'],
                'type' => !empty($config['disabled']) ? 'holdout' : ($config['type'] ?? ''),
                'product_count' => $config['product_count'] ?? '',
                'weights' => isset($config['weights']) ? json_encode($config['weights']) : '',
            ];
        }
        return $values;
    }
}
