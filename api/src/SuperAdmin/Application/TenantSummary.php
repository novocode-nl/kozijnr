<?php

namespace App\SuperAdmin\Application;

use App\Tenancy\Domain\Tenant;

/**
 * Read-model DTO for the super-admin tenant list: exactly the two fields
 * the DoD calls for (subdomain + aanmaakdatum), nothing from `Tenant`'s
 * internals such as the Postgres schema name leaks through this boundary.
 */
final class TenantSummary
{
    public function __construct(
        public readonly string $subdomain,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * Adapts a App\Tenancy\Domain\Tenant (owned by the Tenancy bounded
     * context) to this SuperAdmin-specific read model. Used both after
     * listing tenants (ListTenants) and after provisioning a new one
     * (TenantAdminController, which calls Tenancy's own ProvisionTenant use
     * case directly rather than through a SuperAdmin-owned wrapper).
     */
    public static function fromTenant(Tenant $tenant): self
    {
        return new self($tenant->getSubdomain(), $tenant->getCreatedAt());
    }
}
