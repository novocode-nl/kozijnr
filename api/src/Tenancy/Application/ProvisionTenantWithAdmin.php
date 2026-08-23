<?php

namespace App\Tenancy\Application;

use App\TenantUser\Application\CreateTenantUser;
use App\TenantUser\Domain\TenantUser;
use Doctrine\DBAL\Connection;

/**
 * Orchestrates the full "create a tenant" flow: provisions the tenant
 * (schema + migrations + registration, via ProvisionTenant), then creates
 * a tenant-admin account for it inside its freshly migrated schema (via
 * CreateTenantUser from the TenantUser context) with a generated password.
 *
 * This is the single Application-layer use case CreateTenantController
 * calls — per this codebase's controller SRP rule, a controller may only
 * ever call one use case, so the two-step orchestration lives here instead
 * of in the controller.
 *
 * The tenant-admin email is caller-supplied (KOZ-27 rework: previously
 * auto-generated from the subdomain) — validated here before anything is
 * provisioned, same "fail before creating anything" discipline as
 * ProvisionTenant applies to the name/slug. The generated password is
 * unchanged.
 *
 * If the tenant is provisioned successfully but admin-account creation
 * then fails (should be exceedingly rare, e.g. a race on the email unique
 * constraint), the tenant is deliberately *not* rolled back — dropping a
 * freshly migrated schema over a secondary step failing would be a worse
 * outcome than a tenant that temporarily has no admin account, which can
 * be created after the fact via `tenant-user:create`.
 */
final class ProvisionTenantWithAdmin
{
    public function __construct(
        private readonly ProvisionTenant $provisionTenant,
        private readonly CreateTenantUser $createTenantUser,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(string $name, string $slug, string $adminEmail): ProvisionedTenantWithAdmin
    {
        $email = trim($adminEmail);

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Tenant-admin email must be a valid email address.');
        }

        $tenant = ($this->provisionTenant)($name, $slug);

        $password = bin2hex(random_bytes(12));

        $this->connection->executeStatement(sprintf(
            'SET search_path TO %s, public',
            $this->connection->quoteSingleIdentifier($tenant->getSchemaName()),
        ));

        try {
            ($this->createTenantUser)($email, $password, [TenantUser::ROLE_TENANT_ADMIN]);
        } finally {
            $this->connection->executeStatement('SET search_path TO public');
        }

        return new ProvisionedTenantWithAdmin($tenant, $email, $password);
    }
}
