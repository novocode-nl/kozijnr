<?php

namespace App\TenantUser\Application;

use App\Shared\Domain\Exception\ValidationException;
use App\Tenancy\Domain\Exception\TenantNotFoundException;
use App\Tenancy\Domain\TenantRepositoryInterface;
use App\TenantUser\Domain\TenantUser;
use Doctrine\DBAL\Connection;

/**
 * Creates an *additional* tenant-user account inside one already-existing
 * tenant's schema (KOZ-31), analogous to the `tenant-user:create` CLI
 * command (App\TenantUser\Infrastructure\Command\CreateTenantUserCommand)
 * but reachable as an HTTP use case for the admin "Gebruikers" tab. Looks
 * the tenant up by subdomain in the public schema, switches search_path
 * into its tenant schema for the duration of the write, then always resets
 * back to `public` — same approach ListTenantUsers and
 * ProvisionTenantWithAdmin already use for the same schema switch.
 *
 * The caller picks a role from the two that exist on TenantUser
 * (ROLE_TENANT_ADMIN / the default ROLE_TENANT_USER) — this ticket adds no
 * new roles, only the ability to choose between the two that already
 * exist. A generated password is returned once, alongside the created
 * user, the same one-time-credentials pattern
 * ProvisionTenantWithAdmin/CreateTenantController already use.
 */
final class CreateTenantUserForTenant
{
    /** @var list<string> */
    private const ALLOWED_ROLES = [TenantUser::ROLE_TENANT_ADMIN, TenantUser::DEFAULT_ROLE];

    public function __construct(
        private readonly Connection $connection,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly CreateTenantUser $createTenantUser,
    ) {
    }

    public function __invoke(string $subdomain, string $email, string $role): CreatedTenantUserForTenant
    {
        $email = trim($email);

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::create(
                'Tenant-user email must be a valid email address.',
                'tenants.error.userEmailInvalid',
            );
        }

        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            throw ValidationException::create(
                sprintf('Invalid tenant-user role "%s".', $role),
                'tenants.error.userRoleInvalid',
            );
        }

        $this->connection->executeStatement('SET search_path TO public');

        $tenant = $this->tenantRepository->findBySubdomain($subdomain);
        if ($tenant === null) {
            throw TenantNotFoundException::forSubdomain($subdomain);
        }

        $password = bin2hex(random_bytes(12));

        $this->connection->executeStatement(sprintf(
            'SET search_path TO %s, public',
            $this->connection->quoteSingleIdentifier($tenant->getSchemaName()),
        ));

        try {
            $tenantUser = ($this->createTenantUser)($email, $password, [$role]);
        } finally {
            $this->connection->executeStatement('SET search_path TO public');
        }

        return new CreatedTenantUserForTenant($tenantUser->getEmail(), $tenantUser->getRoles(), $password);
    }
}
