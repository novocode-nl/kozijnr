<?php

namespace App\SuperAdmin\Application;

use App\Tenancy\Domain\Tenant;
use App\Tenancy\Domain\TenantRepositoryInterface;

/**
 * Read-only query for the super-admin tenant list. Never mutates state,
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
            static fn (Tenant $tenant) => new TenantSummary($tenant->getSubdomain(), $tenant->getCreatedAt()),
            $this->tenantRepository->findAll(),
        );
    }
}
