<?php

namespace App\Tenancy\Application;

use App\Tenancy\Domain\Exception\TenantNotFoundException;
use App\Tenancy\Domain\Tenant;
use App\Tenancy\Domain\TenantRepositoryInterface;
use App\Tenancy\Domain\TenantSchemaContextInterface;

/**
 * Archives (soft-deletes) an existing tenant. The tenant row and its
 * Postgres schema are both kept — only `archivedAt` is set — so this is
 * always reversible via UnarchiveTenant. An archived tenant becomes
 * structurally unreachable (see TenantResolverListener), including for
 * login, without any data being destroyed.
 */
final class ArchiveTenant
{
    public function __construct(
        private readonly TenantSchemaContextInterface $schemaContext,
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {
    }

    public function __invoke(string $subdomain): Tenant
    {
        $this->schemaContext->resetToPublic();

        $tenant = $this->tenantRepository->findBySubdomain($subdomain);
        if ($tenant === null) {
            throw TenantNotFoundException::forSubdomain($subdomain);
        }

        $tenant->archive();
        $this->tenantRepository->update($tenant);

        return $tenant;
    }
}
