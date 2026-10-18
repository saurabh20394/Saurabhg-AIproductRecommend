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
use Saurabhg\AIproductRecommend\Model\Catalog\IndexQueue;
use Saurabhg\AIproductRecommend\Model\Catalog\ProductIndexer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento airecommend:reindex [--products=1,2] [--store=1] [--queue]
 */
class ReindexCommand extends Command
{
    /**
     * @param ProductIndexer $indexer
     * @param IndexQueue $indexQueue
     * @param State $appState
     */
    public function __construct(
        private readonly ProductIndexer $indexer,
        private readonly IndexQueue $indexQueue,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('airecommend:reindex')
            ->setDescription('Generate product embeddings / vector neighbors and sync the catalog to the AI service')
            ->addOption('products', 'p', InputOption::VALUE_REQUIRED, 'Comma-separated product IDs (default: all)')
            ->addOption('store', 's', InputOption::VALUE_REQUIRED, 'Store view ID (default: all enabled store views)')
            ->addOption('queue', null, InputOption::VALUE_NONE, 'Only queue the work for the consumer / cron');
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
            unset($e); // area already set
        }
        $stores = $input->getOption('store') ? [(int)$input->getOption('store')] : null;
        $ids = $input->getOption('products')
            ? array_map('intval', explode(',', (string)$input->getOption('products'))) : [];

        $queued = $ids ? $this->indexQueue->enqueue($ids, $stores) : $this->indexQueue->enqueueAll($stores);
        $output->writeln(sprintf('<info>%d product(s) marked for indexing.</info>', $queued));
        if ($input->getOption('queue')) {
            return Cli::RETURN_SUCCESS;
        }
        $totals = ['indexed' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0, 'synced' => 0];
        do {
            $batch = $this->indexer->reindexPending(500);
            foreach ($batch as $key => $count) {
                $totals[$key] += $count;
            }
            $output->writeln(sprintf(
                'embedded %d, unchanged %d, skipped %d, failed %d, synced %d',
                $batch['indexed'],
                $batch['unchanged'],
                $batch['skipped'],
                $batch['failed'],
                $batch['synced']
            ));
        } while (($batch['indexed'] + $batch['unchanged'] + $batch['skipped']) > 0 && $batch['failed'] === 0);
        $output->writeln(sprintf(
            '<info>Done: %d embedded, %d unchanged, %d skipped, %d failed.</info>',
            $totals['indexed'],
            $totals['unchanged'],
            $totals['skipped'],
            $totals['failed']
        ));
        return $totals['failed'] ? Cli::RETURN_FAILURE : Cli::RETURN_SUCCESS;
    }
}
