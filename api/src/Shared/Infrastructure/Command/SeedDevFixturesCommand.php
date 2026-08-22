<?php

namespace App\Shared\Infrastructure\Command;

use App\Tenancy\Application\ProvisionTenant;
use App\Tenancy\Domain\Exception\TenantAlreadyExistsException;
use App\Tenancy\Domain\TenantRepositoryInterface;
use App\TenantUser\Application\CreateTenantUser;
use App\TenantUser\Domain\Exception\TenantUserAlreadyExistsException;
use App\User\Application\CreateSuperAdmin;
use App\User\Domain\Exception\UserAlreadyExistsException;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Seeds the standard dev/test-only accounts (KOZ-22) so every dev/test
 * environment — including per-worktree stacks — always has the same known
 * credentials to log in and test with:
 *
 * - super-admin "admin@kozijnr.nl" / "password" (public schema), via
 *   `super-admin:create`'s own use case (KOZ-8).
 * - tenant "tenant1", via `tenant:provision`'s own use case (KOZ-7).
 * - tenant-user "tenant@kozijnr.nl" / "password" inside tenant1's schema,
 *   via `tenant-user:create`'s own use case (KOZ-11).
 *
 * Idempotent: each step is skipped (not failed) if its account/tenant
 * already exists, so this command is safe to run on every environment
 * bootstrap, not just the first one.
 *
 * Hard-refuses to run unless the environment is explicitly "dev" or "test"
 * — this fixed, publicly known password must never end up in anything other
 * than a local/dev or automated-test environment. This is an allowlist, not
 * a blocklist: any environment name that isn't explicitly "dev"/"test"
 * (including "prod", "staging", a typo, or any future/unknown environment
 * name) is refused, so a new non-dev/test environment can never accidentally
 * fall through. This is a loud failure (a clear console error, non-zero exit
 * code), not a silent no-op, so a misconfigured pipeline can't mistake "did
 * nothing" for "succeeded".
 */
#[AsCommand(
    name: 'app:seed-dev-fixtures',
    description: 'Seeds the standard dev/test super-admin, tenant1 and tenant-user accounts. Only runs in dev/test.',
)]
final class SeedDevFixturesCommand extends Command
{
    private const SUPER_ADMIN_EMAIL = 'admin@kozijnr.nl';
    private const TENANT_NAME = 'tenant1';
    private const TENANT_USER_EMAIL = 'tenant@kozijnr.nl';
    private const FIXED_PASSWORD = 'password';

    /** @var string[] */
    private const ALLOWED_ENVIRONMENTS = ['dev', 'test'];

    public function __construct(
        private readonly CreateSuperAdmin $createSuperAdmin,
        private readonly ProvisionTenant $provisionTenant,
        private readonly CreateTenantUser $createTenantUser,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.environment%')] private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!\in_array($this->environment, self::ALLOWED_ENVIRONMENTS, true)) {
            $io->error(sprintf(
                'Refusing to seed dev fixtures: APP_ENV/kernel environment is "%s". '
                . 'This command creates accounts with a fixed, publicly known password '
                . 'and must never run against a production environment. It only runs '
                . 'when the environment is explicitly one of: %s.',
                $this->environment,
                implode(', ', self::ALLOWED_ENVIRONMENTS),
            ));

            return Command::FAILURE;
        }

        try {
            $this->seedSuperAdmin($io);
            $this->seedTenantAndTenantUser($io);
        } catch (\Throwable $exception) {
            // Deliberately broad, same reasoning as ProvisionTenantCommand:
            // the specific "already exists" exceptions are handled per step
            // below for idempotency, so anything reaching here is genuinely
            // unexpected (e.g. a pre-existing raw schema conflict) and must
            // be reported cleanly rather than escaping as an uncaught stack
            // trace.
            $this->logger->error('app:seed-dev-fixtures failed: {message}', [
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function seedSuperAdmin(SymfonyStyle $io): void
    {
        try {
            ($this->createSuperAdmin)(self::SUPER_ADMIN_EMAIL, self::FIXED_PASSWORD);
            $io->success(sprintf('Created super admin "%s".', self::SUPER_ADMIN_EMAIL));
        } catch (UserAlreadyExistsException) {
            $io->note(sprintf('Super admin "%s" already exists — reusing it.', self::SUPER_ADMIN_EMAIL));
        }
    }

    private function seedTenantAndTenantUser(SymfonyStyle $io): void
    {
        try {
            $tenant = ($this->provisionTenant)(self::TENANT_NAME);
            $io->success(sprintf('Provisioned tenant "%s" (schema "%s").', $tenant->getSubdomain(), $tenant->getSchemaName()));
        } catch (TenantAlreadyExistsException) {
            $io->note(sprintf('Tenant "%s" already exists — reusing it.', self::TENANT_NAME));
        }

        // Defensive reset, same reasoning as ProvisionTenant/TenantResolverListener:
        // never assume the connection is already on `public`.
        $this->connection->executeStatement('SET search_path TO public');
        $tenant = $this->tenantRepository->findBySubdomain(self::TENANT_NAME);

        if ($tenant === null) {
            // Provisioning above threw something other than
            // TenantAlreadyExistsException (e.g. a pre-existing raw schema
            // conflict) and was allowed to propagate, so this line is
            // unreachable in that case — this guard only covers the
            // in-principle case of the tenant vanishing between the two
            // lookups above. Throwing (rather than just reporting via $io
            // and returning) ensures execute()'s catch block still turns
            // this into a non-zero exit code — no silent no-op.
            throw new \RuntimeException(sprintf('Tenant "%s" could not be found after provisioning.', self::TENANT_NAME));
        }

        $this->connection->executeStatement(sprintf(
            'SET search_path TO %s, public',
            $this->connection->quoteSingleIdentifier($tenant->getSchemaName()),
        ));

        try {
            ($this->createTenantUser)(self::TENANT_USER_EMAIL, self::FIXED_PASSWORD, []);
            $io->success(sprintf('Created tenant user "%s" for tenant "%s".', self::TENANT_USER_EMAIL, $tenant->getSubdomain()));
        } catch (TenantUserAlreadyExistsException) {
            $io->note(sprintf('Tenant user "%s" already exists — reusing it.', self::TENANT_USER_EMAIL));
        } finally {
            $this->connection->executeStatement('SET search_path TO public');
        }
    }
}
