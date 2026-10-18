<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Ui\DataProvider;

/**
 * Adds entity-specific data to an edit form
 */
interface FormDataModifierInterface
{
    /**
     * @param array $values entity data
     * @return array
     */
    public function modify(array $values): array;
}
