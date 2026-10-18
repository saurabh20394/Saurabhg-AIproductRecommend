<?php
/**
 * Saurabhg_AIproductRecommend
 *
 * @copyright Copyright (C) 2026 Saurabh Gupta
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Saurabhg\AIproductRecommend\Console\Command;

use Magento\Framework\Console\Cli;
use Saurabhg\AIproductRecommend\Api\Data\HealthComponentInterface;
use Saurabhg\AIproductRecommend\Model\HealthCheck;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento airecommend:health - dependency state (non-zero exit code when something is down)
 */
class HealthCommand extends Command
{
    /**
     * @param HealthCheck $healthCheck
     */
    public function __construct(
        private readonly HealthCheck $healthCheck
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('airecommend:health')->setDescription('Show AI recommendation dependency health');
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = new Table($output);
        $table->setHeaders(['Component', 'Status', 'Details']);
        $down = false;
        foreach ($this->healthCheck->check() as $component) {
            $table->addRow([$component->getName(), $component->getStatus(), $component->getMessage()]);
            $down = $down || $component->getStatus() === HealthComponentInterface::STATUS_DOWN;
        }
        $table->render();
        return $down ? Cli::RETURN_FAILURE : Cli::RETURN_SUCCESS;
    }
}
