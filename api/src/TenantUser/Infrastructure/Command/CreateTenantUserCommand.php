<?php

namespace App\TenantUser\Infrastructure\Command;

use App\Tenancy\Domain\TenantRepositoryInterface;
use App\Tenancy\Domain\TenantSchemaContextInterface;
use App\TenantUser\Application\CreateTenantUser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Creates a tenant-user account inside one tenant's schema. No
 * self-service registration route exists — this console command is the
 * only way to bootstrap a tenant-user account today.
 *
 * Looks the tenant up by subdomain in the public `tenants` table first,
 * then points the connection's search_path at that tenant's schema before
 * calling CreateTenantUser.
 */
#[AsCommand(
    name: 'tenant-user:create',
    description: 'Creates a tenant-user account inside one tenant\'s schema.',
)]
final class CreateTenantUserCommand extends Command
{
    public function __construct(
        private readonly TenantSchemaContextInterface $schemaContext,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly CreateTenantUser $createTenantUser,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('subdomain', InputArgument::REQUIRED, 'Tenant subdomain the account belongs to (e.g. "acme").')
            ->addArgument('email', InputArgument::REQUIRED, 'Tenant-user login email.')
            ->addArgument('password', InputArgument::OPTIONAL, 'Tenant-user password (prompted interactively if omitted).')
            ->addArgument('role', InputArgument::OPTIONAL, 'Role to assign (defaults to ROLE_TENANT_USER).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $subdomain = (string) $input->getArgument('subdomain');
        $email = (string) $input->getArgument('email');
        $password = $input->getArgument('password');
        $role = $input->getArgument('role');

        if ($password === null) {
            $question = new Question('Password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $io->askQuestion($question);
        }

        $password = (string) $password;

        if ($password === '') {
            $io->error('Password cannot be empty.');

            return Command::INVALID;
        }

        $this->schemaContext->resetToPublic();
        $tenant = $this->tenantRepository->findBySubdomain($subdomain);

        if ($tenant === null) {
            $io->error(sprintf('No tenant found for subdomain "%s".', $subdomain));

            return Command::FAILURE;
        }

        try {
            $roles = is_string($role) && $role !== '' ? [$role] : [];
            $tenantUser = $this->schemaContext->runInSchema(
                $tenant->getSchemaName(),
                fn () => ($this->createTenantUser)($email, $password, $roles),
            );
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Created tenant user "%s" for tenant "%s".',
            $tenantUser->getEmail(),
            $tenant->getSubdomain(),
        ));

        return Command::SUCCESS;
    }
}
