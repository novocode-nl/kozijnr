<?php

namespace App\Tenancy\Application;

use App\Tenancy\Domain\TenantRepositoryInterface;

/**
 * Read-only query for the tenant list (used by the admin tenant-management
 * API, KOZ-8). Never mutates state,
 * bypasses nothing special in the domain since Tenant has no invariants
 * relevant to a plain listing — this is a direct read-model projection over
 * TenantRepositoryInterface::findAll(), which already only ever queries the
 * public schema.
 */
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
