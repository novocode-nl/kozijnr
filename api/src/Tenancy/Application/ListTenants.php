<?php

namespace App\Tenancy\Application;

use App\Tenancy\Domain\TenantRepositoryInterface;

/** Read-only query for the tenant list, used by the admin tenant-management API. */
final class ListTenants
{
    public function __construct(private readonly TenantRepositoryInterface $tenantRepository)
    {
    }

    /** @return TenantSummary[] */
    public function __invoke(): array
    {
        return array_map(
            TenantSummary::fromTenant(...),
            $this->tenantRepository->findAll(),
        );
    }
}
