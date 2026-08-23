<?php

namespace App\Tenancy\Application;

use App\Tenancy\Domain\TenantRepositoryInterface;

/** Read-only query for the tenant list, used by the admin tenant-management API. */
final class ListTenants
{
    public function __construct(private readonly TenantRepositoryInterface $tenantRepository)
    {
    }

    /**
     * @return TenantSummary[]
     */
    public function __invoke(bool $includeArchived = false): array
    {
        $tenants = $includeArchived
            ? $this->tenantRepository->findAllArchived()
            : $this->tenantRepository->findAllActive();

        return array_map(TenantSummary::fromTenant(...), $tenants);
    }
}
