<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Edit / delete (/ results) row actions; paths configured in the listing XML
 */
class Actions extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @inheritdoc
     */
    public function prepareDataSource(array $dataSource)
    {
        $config = (array)$this->getData('config');
        $idField = (string)($config['idField'] ?? 'entity_id');
        $name = (string)$this->getData('name');
        foreach ($dataSource['data']['items'] ?? [] as $index => $item) {
            if (!isset($item[$idField])) {
                continue;
            }
            $id = (int)$item[$idField];
            $actions = [
                'edit' => [
                    'href' => $this->urlBuilder->getUrl($config['editUrlPath'], ['id' => $id]),
                    'label' => __('Edit'),
                ],
            ];
            if (!empty($config['reportUrlPath'])) {
                $actions['report'] = [
                    'href' => $this->urlBuilder->getUrl($config['reportUrlPath'], ['id' => $id]),
                    'label' => __('Results'),
                ];
            }
            $actions['delete'] = [
                'href' => $this->urlBuilder->getUrl($config['deleteUrlPath'], ['id' => $id]),
                'label' => __('Delete'),
                'confirm' => [
                    'title' => __('Delete'),
                    'message' => __('Are you sure you want to delete this record?'),
                ],
                'post' => true,
            ];
            $dataSource['data']['items'][$index][$name] = $actions;
        }
        return $dataSource;
    }
}
