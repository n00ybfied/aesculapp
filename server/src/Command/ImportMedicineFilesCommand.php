<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\{ActiveTenantProvider, MedicineFileImporter};
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:medicines:import', description: 'Import local APGDA catalogue and/or ApoScout stock log.')]
final class ImportMedicineFilesCommand extends Command
{
    public function __construct(private readonly MedicineFileImporter $importer, private readonly ActiveTenantProvider $tenant)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('catalogue', null, InputOption::VALUE_REQUIRED, 'Local APGDA.001 path');
        $this->addOption('stock-log', null, InputOption::VALUE_REQUIRED, 'Local ApoScout log path');
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Reimport an unchanged file after parser corrections.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $catalogue = $input->getOption('catalogue');
        $stock = $input->getOption('stock-log');
        if (!is_string($catalogue) && !is_string($stock)) {
            $output->writeln('<error>Specify --catalogue and/or --stock-log.</error>');
            return Command::INVALID;
        }
        try {
            if (is_string($catalogue)) $output->writeln('Catalogue rows: '.$this->importer->importCatalogue($catalogue, (bool) $input->getOption('force')));
            if (is_string($stock)) $output->writeln('Stock rows: '.$this->importer->importStockLog($stock, (int) $this->tenant->get()->getId(), (bool) $input->getOption('force')));
            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $output->writeln('<error>'.$exception->getMessage().'</error>');
            return Command::FAILURE;
        }
    }
}
