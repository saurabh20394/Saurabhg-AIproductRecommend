<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Saurabhg\AIproductRecommend\Cron\Jobs;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento airecommend:calculate - popularity, trending, co-purchase, co-view and analytics now
 */
class CalculateCommand extends Command
{
    /**
     * @param Jobs $jobs
     * @param State $appState
     */
    public function __construct(
        private readonly Jobs $jobs,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('airecommend:calculate')
            ->setDescription('Recalculate recommendation signals (stats, relations, analytics aggregates)');
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_CRONTAB);
        } catch (\Exception $e) {
            unset($e);
        }
        foreach ($this->jobs->calculateStats() as $storeId => $count) {
            $output->writeln(sprintf('store %d: %d products with popularity/trending stats', $storeId, $count));
        }
        foreach ($this->jobs->calculateRelations() as $storeId => $counts) {
            $output->writeln(sprintf(
                'store %d: %d co-purchase, %d co-view source products',
                $storeId,
                $counts['co_purchase'],
                $counts['co_view']
            ));
        }
        $output->writeln(sprintf('%d analytics aggregate rows', $this->jobs->aggregateAnalytics()));
        $output->writeln('<info>Done.</info>');
        return Cli::RETURN_SUCCESS;
    }
}
