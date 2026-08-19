<?php

namespace App\SuperAdmin\Application;

use App\Tenancy\Application\ProvisionTenant;

/**
 * The super-admin "create tenant" use case. Deliberately thin: all the
 * actual provisioning logic (schema creation, migrations, registration,
 * cleanup on failure) is KOZ-7's ProvisionTenant, which this only adapts to
 * the SuperAdmin context's read model (TenantSummary) so the Presentation
 * layer here never has to know about App\Tenancy\Domain\Tenant directly.
 */
final class CreateTenant
{
    public function __construct(private readonly ProvisionTenant $provisionTenant)
    {
    }

    public function __invoke(string $name): TenantSummary
    {
        $tenant = ($this->provisionTenant)($name);

        return new TenantSummary($tenant->getSubdomain(), $tenant->getCreatedAt());
    }
}
