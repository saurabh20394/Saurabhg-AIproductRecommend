<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\ViewModel\Adminhtml;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Saurabhg\AIproductRecommend\Model\Analytics\Aggregator;
use Saurabhg\AIproductRecommend\Model\Analytics\Reports;
use Saurabhg\AIproductRecommend\Model\Experiment;
use Saurabhg\AIproductRecommend\Model\ExperimentFactory;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Experiment as ExperimentResource;

/**
 * Variant comparison: CTR, conversion, AOV and attributed revenue with uplift vs control
 */
class ExperimentReport implements ArgumentInterface
{
    /**
     * @param RequestInterface $request
     * @param ExperimentFactory $experimentFactory
     * @param ExperimentResource $resource
     * @param Reports $reports
     * @param Aggregator $aggregator
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ExperimentFactory $experimentFactory,
        private readonly ExperimentResource $resource,
        private readonly Reports $reports,
        private readonly Aggregator $aggregator
    ) {
    }

    /**
     * @return Experiment|null
     */
    public function getExperiment(): ?Experiment
    {
        $experiment = $this->experimentFactory->create();
        $this->resource->load($experiment, (int)$this->request->getParam('id'));
        return $experiment->getId() ? $experiment : null;
    }

    /**
     * @param Experiment $experiment
     * @return array[]
     */
    public function getRows(Experiment $experiment): array
    {
        try {
            $this->aggregator->aggregate(gmdate('Y-m-d', time() - 86400), gmdate('Y-m-d'));
        } catch (\Throwable $e) {
            // stale aggregates are acceptable here
            unset($e);
        }
        $id = (int)$experiment->getId();
        $metrics = $this->reports->getByVariant($id);
        $assignments = $this->resource->getAssignmentCounts($id);
        $rows = [];
        $control = null;
        foreach ($this->resource->getVariants($id) as $variantId => $variant) {
            $row = ($metrics[$variantId] ?? $this->reports->withRates([])) + [
                'code' => $variant['code'],
                'name' => $variant['name'],
                'allocation' => (int)$variant['allocation'],
                'is_control' => (bool)(int)$variant['is_control'],
                'assigned' => $assignments[$variantId] ?? 0,
            ];
            if ($row['is_control']) {
                $control = $row;
            }
            $rows[] = $row;
        }
        foreach ($rows as $index => $row) {
            foreach (['ctr', 'conversion_rate', 'aov'] as $metric) {
                $base = $control[$metric] ?? 0;
                $rows[$index][$metric . '_lift'] = !$row['is_control'] && $base > 0
                    ? round(($row[$metric] / $base - 1) * 100, 1) : null;
            }
            $rows[$index]['revenue_per_visitor'] = $row['assigned'] ? round($row['revenue'] / $row['assigned'], 2) : 0;
        }
        return $rows;
    }
}
