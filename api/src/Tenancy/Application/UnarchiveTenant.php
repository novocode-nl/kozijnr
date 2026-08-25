<?php

namespace App\Tenancy\Application;

use App\Tenancy\Domain\Exception\TenantNotFoundException;
use App\Tenancy\Domain\Tenant;
use App\Tenancy\Domain\TenantRepositoryInterface;
use App\Tenancy\Domain\TenantSchemaContextInterface;

/**
 * Reverses ArchiveTenant: clears `archivedAt` so the tenant becomes
 * reachable again (findable by subdomain, loginable) and shows back up in
 * the default (active) admin tenant overview.
 */
final class UnarchiveTenant
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

        $tenant->unarchive();
        $this->tenantRepository->update($tenant);

        return $tenant;
    }
}
