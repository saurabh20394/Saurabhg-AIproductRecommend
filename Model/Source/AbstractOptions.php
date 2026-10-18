<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Static code => label option list
 */
abstract class AbstractOptions implements OptionSourceInterface
{
    /**
     * @return array<string|int, \Magento\Framework\Phrase|string>
     */
    abstract public function getOptions(): array;

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $result = [];
        foreach ($this->getOptions() as $value => $label) {
            $result[] = ['value' => (string)$value, 'label' => $label];
        }
        return $result;
    }

    /**
     * @param string $value
     * @return string
     */
    public function getLabel(string $value): string
    {
        return (string)($this->getOptions()[$value] ?? $value);
    }
}
