<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Saurabhg\AIproductRecommend\Model\Recommendation\ProductDataLoader;

/**
 * Product attribute "Exclude from AI Recommendations" (store view scope)
 */
class AddExcludeAttribute implements DataPatchInterface, PatchRevertableInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        if ($eavSetup->getAttributeId(Product::ENTITY, ProductDataLoader::EXCLUDE_ATTRIBUTE)) {
            return $this;
        }
        $eavSetup->addAttribute(Product::ENTITY, ProductDataLoader::EXCLUDE_ATTRIBUTE, [
            'type' => 'int',
            'label' => 'Exclude from AI Recommendations',
            'input' => 'boolean',
            'source' => Boolean::class,
            'global' => ScopedAttributeInterface::SCOPE_STORE,
            'default' => '0',
            'required' => false,
            'user_defined' => true,
            'visible' => true,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => false,
            'is_used_in_grid' => true,
            'is_filterable_in_grid' => true,
            'group' => 'Search Engine Optimization',
            'sort_order' => 200,
            'note' => 'Excluded products never appear in AI recommendations.',
        ]);
        return $this;
    }

    /**
     * Removing the attribute deletes merchant data: only through explicit patch revert
     *
     * @inheritdoc
     */
    public function revert()
    {
        $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup])
            ->removeAttribute(Product::ENTITY, ProductDataLoader::EXCLUDE_ATTRIBUTE);
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
