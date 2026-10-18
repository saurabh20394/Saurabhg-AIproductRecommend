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
use Saurabhg\AIproductRecommend\Model\Personalization\VisitorIdentity;
use Saurabhg\AIproductRecommend\Model\Privacy\DataEraser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento airecommend:privacy:forget --customer=ID | --visitor=ID
 */
class ForgetCommand extends Command
{
    /**
     * @param DataEraser $dataEraser
     */
    public function __construct(
        private readonly DataEraser $dataEraser
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('airecommend:privacy:forget')
            ->setDescription('Delete the recommendation profile and anonymize behavioral data of a customer or visitor')
            ->addOption('customer', 'c', InputOption::VALUE_REQUIRED, 'Customer ID')
            ->addOption('visitor', 'v', InputOption::VALUE_REQUIRED, 'Visitor ID (aipr_vid cookie value)');
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $customerId = (int)$input->getOption('customer');
        $visitorId = (string)$input->getOption('visitor');
        if ($customerId > 0) {
            $rows = $this->dataEraser->forgetCustomer($customerId);
        } elseif (VisitorIdentity::isValid($visitorId)) {
            $rows = $this->dataEraser->forgetVisitor($visitorId);
        } else {
            $output->writeln('<error>Provide --customer=ID or a valid --visitor=ID.</error>');
            return Cli::RETURN_FAILURE;
        }
        $output->writeln(sprintf('<info>%d row(s) deleted or anonymized.</info>', $rows));
        return Cli::RETURN_SUCCESS;
    }
}
