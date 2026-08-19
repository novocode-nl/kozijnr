<?php

namespace App\Tenancy\Presentation\Command;

use App\Tenancy\Application\ProvisionTenant;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'tenant:provision',
    description: 'Provisions a new tenant: creates its Postgres schema, runs the tenant migrations on it, and registers it in the public tenants table.',
)]
final class ProvisionTenantCommand extends Command
{
    public function __construct(private readonly ProvisionTenant $provisionTenant)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'name',
            InputArgument::REQUIRED,
            'Tenant name/subdomain: lowercase alphanumeric segments separated by single hyphens '
            . '(e.g. "acme", "acme-bv"). Used as the subdomain as-is; the Postgres schema name '
            . '("tenant_<name>", hyphens turned into underscores) is derived from it.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string) $input->getArgument('name');

        try {
            $tenant = ($this->provisionTenant)($name);
        } catch (\Throwable $exception) {
            // Deliberately broad: ProvisionTenant is expected to raise its
            // own domain exceptions (invalid name, duplicate
            // subdomain/schema, pre-existing raw schema), but every failure
            // route — including a raw Doctrine\DBAL exception from e.g. a
            // race between two concurrent `tenant:provision` runs for the
            // same name — must be reported cleanly here rather than
            // escaping as an uncaught stack trace. ProvisionTenant itself is
            // responsible for leaving no half-provisioned state behind
            // (dropping the schema again) before this catch ever runs.
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Provisioned tenant "%s" (schema "%s").',
            $tenant->getSubdomain(),
            $tenant->getSchemaName(),
        ));

        return Command::SUCCESS;
    }
}
