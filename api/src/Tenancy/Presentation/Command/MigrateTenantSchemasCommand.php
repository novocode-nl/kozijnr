<?php

namespace App\Tenancy\Presentation\Command;

use App\Tenancy\Application\MigrateAllTenantSchemas;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'tenant:migrate',
    description: 'Migrates existing tenant schemas to the latest tenant migration version.',
)]
final class MigrateTenantSchemasCommand extends Command
{
    public function __construct(private readonly MigrateAllTenantSchemas $migrateAllTenantSchemas)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'all',
            null,
            InputOption::VALUE_NONE,
            'Migrate every registered tenant schema to the latest tenant migration version.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$input->getOption('all')) {
            $io->error('Specify --all to migrate every tenant schema to the latest tenant migration version.');

            return Command::INVALID;
        }

        $result = ($this->migrateAllTenantSchemas)();

        foreach ($result->succeeded as $schemaName) {
            $io->writeln(sprintf('<info>OK</info>     %s', $schemaName));
        }

        foreach ($result->failed as $schemaName => $message) {
            $io->writeln(sprintf('<error>FAILED</error> %s: %s', $schemaName, $message));
        }

        $io->writeln(sprintf(
            '%d succeeded, %d failed.',
            count($result->succeeded),
            count($result->failed),
        ));

        return $result->isFullySuccessful() ? Command::SUCCESS : Command::FAILURE;
    }
}
