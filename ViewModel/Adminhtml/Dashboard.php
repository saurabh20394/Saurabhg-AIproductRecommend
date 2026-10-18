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
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Saurabhg\AIproductRecommend\Api\Data\HealthComponentInterface;
use Saurabhg\AIproductRecommend\Api\HealthCheckInterface;
use Saurabhg\AIproductRecommend\Logger\Logger;
use Saurabhg\AIproductRecommend\Model\Analytics\Aggregator;
use Saurabhg\AIproductRecommend\Model\Analytics\Reports;
use Saurabhg\AIproductRecommend\Model\HealthCheck;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ProductIndex;
use Saurabhg\AIproductRecommend\Model\ResourceModel\ProductStat;
use Saurabhg\AIproductRecommend\Model\ResourceModel\Unit\CollectionFactory as UnitCollectionFactory;
use Saurabhg\AIproductRecommend\Model\Source\Placement;
use Saurabhg\AIproductRecommend\Model\Source\UnitType;
use Saurabhg\AIproductRecommend\Model\StoreResolver;

/**
 * Dashboard: KPIs with period-over-period change, setup checklist, unit performance, trend and health
 */
class Dashboard implements ArgumentInterface
{
    private const PRESETS = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'];

    private ?array $range = null;
    private ?array $totals = null;
    private ?array $previous = null;
    private ?array $health = null;
    private ?array $units = null;

    /**
     * @param RequestInterface $request
     * @param Reports $reports
     * @param Aggregator $aggregator
     * @param HealthCheckInterface $healthCheck
     * @param StoreManagerInterface $storeManager
     * @param PriceCurrencyInterface $priceCurrency
     * @param UrlInterface $urlBuilder
     * @param Logger $logger
     * @param StoreResolver $storeResolver
     * @param ProductIndex $productIndex
     * @param ProductStat $productStat
     * @param UnitCollectionFactory $unitCollectionFactory
     * @param ResourceConnection $resourceConnection
     * @param UnitType $unitType
     * @param Placement $placement
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly Reports $reports,
        private readonly Aggregator $aggregator,
        private readonly HealthCheckInterface $healthCheck,
        private readonly StoreManagerInterface $storeManager,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly UrlInterface $urlBuilder,
        private readonly Logger $logger,
        private readonly StoreResolver $storeResolver,
        private readonly ProductIndex $productIndex,
        private readonly ProductStat $productStat,
        private readonly UnitCollectionFactory $unitCollectionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly UnitType $unitType,
        private readonly Placement $placement
    ) {
    }

    /**
     * Date range from the request (UTC days), default last 30 days; today's data is aggregated live
     *
     * @return array{from: string, to: string, store_id: int|null, days: int}
     */
    public function getRange(): array
    {
        if ($this->range === null) {
            $from = (string)$this->request->getParam('from');
            $to = (string)$this->request->getParam('to');
            $valid = static fn($date) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
            $to = $valid($to) ? $to : gmdate('Y-m-d');
            $from = $valid($from) ? $from : gmdate('Y-m-d', strtotime($to . ' -29 days'));
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }
            $storeId = (int)$this->request->getParam('store') ?: null;
            $days = (int)round((strtotime($to) - strtotime($from)) / 86400) + 1;
            $this->range = ['from' => $from, 'to' => $to, 'store_id' => $storeId, 'days' => $days];
            if ($to >= gmdate('Y-m-d', time() - 86400)) {
                try {
                    $this->aggregator->aggregate(gmdate('Y-m-d', time() - 86400), gmdate('Y-m-d'));
                } catch (\Throwable $e) {
                    $this->logger->warning('Live analytics aggregation failed', ['error' => $e]);
                }
            }
        }
        return $this->range;
    }

    /**
     * @return array
     */
    public function getTotals(): array
    {
        if ($this->totals === null) {
            $range = $this->getRange();
            $this->totals = $this->reports->getTotals($range['from'], $range['to'], $range['store_id']);
        }
        return $this->totals;
    }

    /**
     * Totals of the equally long period right before the selected one
     *
     * @return array
     */
    public function getPreviousTotals(): array
    {
        if ($this->previous === null) {
            $range = $this->getRange();
            $to = gmdate('Y-m-d', strtotime($range['from'] . ' -1 day'));
            $from = gmdate('Y-m-d', strtotime($to . ' -' . ($range['days'] - 1) . ' days'));
            $this->previous = $this->reports->getTotals($from, $to, $range['store_id']);
        }
        return $this->previous;
    }

    /**
     * Change vs previous period: relative % for counts / money, percentage points for rates
     *
     * @param string $metric
     * @return array{value: float|null, unit: string, direction: string}|null null when there is no baseline
     */
    public function getChange(string $metric): ?array
    {
        $isRate = in_array($metric, ['ctr', 'conversion_rate', 'atc_rate'], true);
        $current = (float)($this->getTotals()[$metric] ?? 0);
        $previous = (float)($this->getPreviousTotals()[$metric] ?? 0);
        if ($isRate) {
            if ((int)($this->getPreviousTotals()['impressions'] ?? 0) === 0) {
                return null;
            }
            $value = round($current - $previous, 2);
            $unit = 'pp';
        } else {
            if ($previous <= 0) {
                return null;
            }
            $value = round(($current / $previous - 1) * 100, 1);
            $unit = '%';
        }
        return ['value' => $value, 'unit' => $unit, 'direction' => $value > 0 ? 'up' : ($value < 0 ? 'down' : 'flat')];
    }

    /**
     * @return array
     */
    public function getAovLift(): array
    {
        $range = $this->getRange();
        return $this->reports->getAovLift($range['from'], $range['to'], $range['store_id']);
    }

    /**
     * Unit performance joined with unit configuration, with revenue share
     *
     * @return array[]
     */
    public function getByUnit(): array
    {
        $range = $this->getRange();
        $rows = $this->reports->getByUnit($range['from'], $range['to'], $range['store_id']);
        $revenue = max(0.0001, array_sum(array_column($rows, 'revenue')));
        $units = $this->getUnits();
        foreach ($rows as $index => $row) {
            $unit = $units[$row['unit_code']] ?? null;
            $rows[$index]['title'] = $unit['title'] ?? $row['unit_code'];
            $rows[$index]['type_label'] = $unit['type_label'] ?? __('Ad-hoc / API');
            $rows[$index]['placement_label'] = $unit['placement_label'] ?? '';
            $rows[$index]['active'] = $unit['active'] ?? null;
            $rows[$index]['edit_url'] = $unit['edit_url'] ?? null;
            $rows[$index]['revenue_share'] = round($row['revenue'] / $revenue * 100, 1);
        }
        return $rows;
    }

    /**
     * Configured units without activity in the period (shown so merchants see what is idle)
     *
     * @return array[]
     */
    public function getIdleUnits(): array
    {
        $active = array_column($this->getByUnit(), 'unit_code');
        return array_values(array_filter(
            $this->getUnits(),
            static fn($unit) => $unit['active'] && !in_array($unit['code'], $active, true)
        ));
    }

    /**
     * Daily rows for every day of the range (missing days are zero)
     *
     * @return array[]
     */
    public function getDaily(): array
    {
        $range = $this->getRange();
        $rows = [];
        foreach ($this->reports->getDaily($range['from'], $range['to'], $range['store_id']) as $row) {
            $rows[$row['period']] = $row;
        }
        $filled = [];
        for ($day = strtotime($range['from']); $day <= strtotime($range['to']); $day += 86400) {
            $date = gmdate('Y-m-d', $day);
            $filled[] = $rows[$date] ?? $this->reports->withRates(['period' => $date]);
        }
        return $filled;
    }

    /**
     * SVG geometry of the trend chart: revenue bars and a clicks line, scaled to the period maximum
     *
     * @param int $width
     * @param int $height
     * @return array{bars: array, line: string, points: array, max_revenue: float, max_clicks: int, labels: array, has_data: bool}
     */
    public function getChart(int $width = 760, int $height = 200): array
    {
        $daily = $this->getDaily();
        $count = max(1, count($daily));
        $maxRevenue = max(array_merge([0.0], array_column($daily, 'revenue')));
        $maxClicks = max(array_merge([0], array_column($daily, 'clicks')));
        $slot = $width / $count;
        $bars = $points = [];
        foreach ($daily as $index => $row) {
            $barHeight = $maxRevenue > 0 ? $row['revenue'] / $maxRevenue * ($height - 10) : 0;
            $x = $index * $slot;
            $bars[] = [
                'x' => round($x + $slot * 0.15, 2),
                'y' => round($height - $barHeight, 2),
                'w' => round(max(1, $slot * 0.7), 2),
                'h' => round($barHeight, 2),
                'title' => sprintf(
                    '%s: %s revenue, %d clicks, %d impressions',
                    $row['period'],
                    $this->formatMoney((float)$row['revenue']),
                    $row['clicks'],
                    $row['impressions']
                ),
            ];
            $pointY = $maxClicks > 0 ? $height - $row['clicks'] / $maxClicks * ($height - 10) : $height;
            $points[] = ['x' => round($x + $slot / 2, 2), 'y' => round($pointY, 2)];
        }
        $labelIndexes = array_unique([0, (int)floor(($count - 1) / 2), $count - 1]);
        $labels = [];
        foreach ($labelIndexes as $index) {
            $labels[] = [
                'x' => round($index * $slot + $slot / 2, 2),
                'text' => date('M j', strtotime($daily[$index]['period'])),
            ];
        }
        return [
            'bars' => $bars,
            'points' => $points,
            'line' => implode(' ', array_map(static fn($p) => $p['x'] . ',' . $p['y'], $points)),
            'max_revenue' => $maxRevenue,
            'max_clicks' => $maxClicks,
            'labels' => $labels,
            'has_data' => $maxRevenue > 0 || $maxClicks > 0,
        ];
    }

    /**
     * Getting-started steps with their current state and the place to fix them
     *
     * @return array{steps: array, done: int, total: int}
     */
    public function getChecklist(): array
    {
        $enabledStores = $this->storeResolver->getStoreIds();
        $indexed = 0;
        foreach ($this->productIndex->getStatusSummary() as $counts) {
            $indexed += $counts[ProductIndex::STATUS_INDEXED] ?? 0;
        }
        $hasStats = false;
        foreach ($enabledStores as $storeId) {
            $hasStats = $hasStats || $this->productStat->hasData($storeId);
        }
        $activeUnits = count(array_filter($this->getUnits(), static fn($unit) => $unit['active']));
        $steps = [
            [
                'done' => (bool)$enabledStores,
                'title' => __('Enable the module'),
                'text' => $enabledStores
                    ? __('Enabled in %1 store view(s).', count($enabledStores))
                    : __('The storefront shows no recommendations until the module is enabled.'),
                'url' => $this->getUrl('adminhtml/system_config/edit', ['section' => 'saurabhg_airecommend']),
                'action' => __('Open configuration'),
            ],
            [
                'done' => $indexed > 0,
                'title' => __('Index the catalog'),
                'text' => $indexed > 0
                    ? __('%1 product embeddings are ready for "similar products".', $indexed)
                    : __('Run bin/magento airecommend:reindex or use the monitor.'),
                'url' => $this->getUrl('saurabhg_airecommend/monitor/index'),
                'action' => __('Open monitor'),
            ],
            [
                'done' => $hasStats,
                'title' => __('Calculate signals'),
                'text' => $hasStats
                    ? __('Popularity and trending scores are available.')
                    : __('Run bin/magento airecommend:calculate or click "Recalculate Signals" in the monitor.'),
                'url' => $this->getUrl('saurabhg_airecommend/monitor/index'),
                'action' => __('Open monitor'),
            ],
            [
                'done' => $activeUnits > 0,
                'title' => __('Place recommendation units'),
                'text' => $activeUnits > 0
                    ? __('%1 active unit(s) on the storefront.', $activeUnits)
                    : __('Create a unit and choose where it appears.'),
                'url' => $this->getUrl('saurabhg_airecommend/unit/index'),
                'action' => __('Manage units'),
            ],
            [
                'done' => $this->isCronRunning(),
                'title' => __('Run cron'),
                'text' => $this->isCronRunning()
                    ? __('Scheduled jobs ran in the last 2 hours.')
                    : __('Cron keeps signals, embeddings and reports up to date: bin/magento cron:run.'),
                'url' => null,
                'action' => null,
            ],
            [
                'done' => $this->hasRecentTraffic(),
                'title' => __('Collect shopper activity'),
                'text' => $this->hasRecentTraffic()
                    ? __('Recommendation impressions were recorded in the last 7 days.')
                    : __('Browse the storefront (after accepting cookies) to see the first impressions.'),
                'url' => null,
                'action' => null,
            ],
        ];
        $done = count(array_filter($steps, static fn($step) => $step['done']));
        return ['steps' => $steps, 'done' => $done, 'total' => count($steps)];
    }

    /**
     * @return HealthComponentInterface[]
     */
    public function getHealth(): array
    {
        if ($this->health === null) {
            $this->health = $this->healthCheck->check();
        }
        return $this->health;
    }

    /**
     * Worst health status (for the summary badge)
     *
     * @return string
     */
    public function getOverallHealth(): string
    {
        $statuses = array_map(static fn($component) => $component->getStatus(), $this->getHealth());
        foreach ([HealthComponentInterface::STATUS_DOWN, HealthComponentInterface::STATUS_WARNING] as $status) {
            if (in_array($status, $statuses, true)) {
                return $status;
            }
        }
        return HealthComponentInterface::STATUS_OK;
    }

    /**
     * Date range presets for the filter bar
     *
     * @return array[] {label, url, active}
     */
    public function getPresets(): array
    {
        $range = $this->getRange();
        $today = gmdate('Y-m-d');
        $presets = [];
        foreach (self::PRESETS as $days => $label) {
            $from = gmdate('Y-m-d', strtotime($today . ' -' . ($days - 1) . ' days'));
            $presets[] = [
                'label' => __($label),
                'url' => $this->getUrl('*/*/index', array_filter(['from' => $from, 'to' => $today, 'store' => $range['store_id']])),
                'active' => $range['to'] === $today && $range['from'] === $from,
            ];
        }
        return $presets;
    }

    /**
     * @return array[] {label, url, hint}
     */
    public function getQuickLinks(): array
    {
        return [
            ['label' => __('Recommendation Units'), 'url' => $this->getUrl('saurabhg_airecommend/unit/index'), 'hint' => __('Where and what to recommend')],
            ['label' => __('Business Rules'), 'url' => $this->getUrl('saurabhg_airecommend/rule/index'), 'hint' => __('Boost, pin and exclude')],
            ['label' => __('Simulator'), 'url' => $this->getUrl('saurabhg_airecommend/simulator/index'), 'hint' => __('See why a product is shown')],
            ['label' => __('A/B Experiments'), 'url' => $this->getUrl('saurabhg_airecommend/experiment/index'), 'hint' => __('Compare strategies')],
            ['label' => __('Reports'), 'url' => $this->getUrl('saurabhg_airecommend/report/index'), 'hint' => __('Daily details and CSV export')],
            ['label' => __('Configuration'), 'url' => $this->getUrl('adminhtml/system_config/edit', ['section' => 'saurabhg_airecommend']), 'hint' => __('Tracking, AI provider, weights')],
        ];
    }

    /**
     * @return string
     */
    public function getModuleVersion(): string
    {
        return $this->healthCheck instanceof HealthCheck ? $this->healthCheck->getModuleVersion() : '';
    }

    /**
     * @param float $amount base currency
     * @return string
     */
    public function formatMoney(float $amount): string
    {
        return $this->priceCurrency->format(
            $amount,
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION,
            0,
            $this->storeManager->getStore()->getBaseCurrencyCode()
        );
    }

    /**
     * @param float|int $value
     * @return string
     */
    public function formatNumber($value): string
    {
        return number_format((float)$value, 0);
    }

    /**
     * @return array<int, string> store ID => name
     */
    public function getStores(): array
    {
        $stores = [];
        foreach ($this->storeManager->getStores() as $store) {
            $stores[(int)$store->getId()] = $store->getWebsite()->getName() . ' / ' . $store->getName();
        }
        return $stores;
    }

    /**
     * @return array[] daily rows per unit (reports / CSV export)
     */
    public function getDailyByUnit(): array
    {
        $range = $this->getRange();
        return $this->reports->getDailyByUnit($range['from'], $range['to'], $range['store_id']);
    }

    /**
     * Daily per-unit rows grouped by day (newest first) with a totals row per day
     *
     * @return array<string, array{rows: array[], totals: array}>
     */
    public function getDailyGroups(): array
    {
        $groups = [];
        foreach ($this->getDailyByUnit() as $row) {
            $unit = $this->getUnits()[$row['unit_code']] ?? null;
            $row['title'] = $unit['title'] ?? $row['unit_code'];
            $row['edit_url'] = $unit['edit_url'] ?? null;
            $groups[$row['period']]['rows'][] = $row;
        }
        krsort($groups);
        foreach ($groups as $period => $group) {
            $sum = [];
            foreach (['impressions', 'clicks', 'add_to_carts', 'orders', 'revenue'] as $metric) {
                $sum[$metric] = array_sum(array_column($group['rows'], $metric));
            }
            $groups[$period]['totals'] = $this->reports->withRates($sum);
        }
        return $groups;
    }

    /**
     * @param string $route
     * @param array $params
     * @return string
     */
    public function getUrl(string $route, array $params = []): string
    {
        return $this->urlBuilder->getUrl($route, $params);
    }

    /**
     * @return array<string, array> unit code => display data
     */
    private function getUnits(): array
    {
        if ($this->units === null) {
            $this->units = [];
            foreach ($this->unitCollectionFactory->create()->setOrder('position', 'ASC') as $unit) {
                $code = (string)$unit->getData('code');
                $this->units[$code] = [
                    'code' => $code,
                    'title' => (string)($unit->getData('title') ?: $code),
                    'type_label' => $this->unitType->getLabel((string)$unit->getData('type')),
                    'placement_label' => $this->placement->getLabel((string)$unit->getData('placement')),
                    'active' => (bool)(int)$unit->getData('status'),
                    'edit_url' => $this->getUrl('saurabhg_airecommend/unit/edit', ['id' => $unit->getId()]),
                ];
            }
        }
        return $this->units;
    }

    /**
     * @return bool
     */
    private function isCronRunning(): bool
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            return (bool)$connection->fetchOne(
                $connection->select()
                    ->from($this->resourceConnection->getTableName('cron_schedule'), ['schedule_id'])
                    ->where('status = ?', 'success')
                    ->where('finished_at >= ?', gmdate('Y-m-d H:i:s', time() - 7200))
                    ->limit(1)
            );
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return bool
     */
    private function hasRecentTraffic(): bool
    {
        $connection = $this->resourceConnection->getConnection();
        return (bool)$connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName('ai_recommendation_impression'), ['id'])
                ->where('created_at >= ?', gmdate('Y-m-d H:i:s', time() - 7 * 86400))
                ->limit(1)
        );
    }
}
