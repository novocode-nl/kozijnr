<?php

namespace App\Tenancy\Application;

use App\Tenancy\Domain\Tenant;

/**
 * Result of ProvisionTenantWithAdmin: the newly provisioned tenant plus the
 * generated tenant-admin credentials. The plain-text password is never
 * persisted anywhere — this is the only place it ever exists outside the
 * hash stored in the tenant schema, so the controller must hand it back to
 * the caller in the same response.
 */
final class ProvisionedTenantWithAdmin
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $tenantAdminEmail,
        public readonly string $tenantAdminPassword,
    ) {
    }
}
