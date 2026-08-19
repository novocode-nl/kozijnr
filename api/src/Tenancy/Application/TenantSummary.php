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
     * (CreateTenantController, which calls this context's own
     * ProvisionTenant use case directly).
     */
    public static function fromTenant(Tenant $tenant): self
    {
        return new self($tenant->getSubdomain(), $tenant->getCreatedAt());
    }

    /**
     * The JSON shape shared by ListTenantsController and
     * CreateTenantController (KOZ-10): kept here, not duplicated per
     * controller, since it's the DTO's own serialization concern rather
     * than business logic either controller should own.
     *
     * @return array{subdomain: string, createdAt: string}
     */
    public function toArray(): array
    {
        return [
            'subdomain' => $this->subdomain,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
