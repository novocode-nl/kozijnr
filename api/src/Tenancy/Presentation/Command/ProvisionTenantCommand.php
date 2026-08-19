<?php

namespace App\Tenancy\Presentation\Command;

use App\Tenancy\Application\ProvisionTenant;
use App\Tenancy\Domain\Exception\InvalidTenantNameException;
use App\Tenancy\Domain\Exception\SchemaAlreadyExistsException;
use App\Tenancy\Domain\Exception\TenantAlreadyExistsException;
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
        } catch (InvalidTenantNameException|TenantAlreadyExistsException|SchemaAlreadyExistsException $exception) {
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
