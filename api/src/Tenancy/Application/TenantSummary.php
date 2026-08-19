<?php

namespace App\Tenancy\Application;

use App\Tenancy\Domain\Tenant;

/**
 * Read-model DTO for the admin tenant list (KOZ-8): exactly the two fields
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
     * Adapts a App\Tenancy\Domain\Tenant to this read model. Used both
     * after listing tenants (ListTenants) and after provisioning a new one
     * (TenantAdminController, which calls this context's own
     * ProvisionTenant use case directly).
     */
    public static function fromTenant(Tenant $tenant): self
    {
        return new self($tenant->getSubdomain(), $tenant->getCreatedAt());
    }
}
