<?php

namespace App\Tenancy\Application;

use App\Tenancy\Domain\Exception\TenantAlreadyExistsException;
use App\Tenancy\Domain\Exception\TenantNotFoundException;
use App\Tenancy\Domain\Tenant;
use App\Tenancy\Domain\TenantName;
use App\Tenancy\Domain\TenantRepositoryInterface;
use App\Tenancy\Domain\TenantSchemaContextInterface;

/**
 * Updates an existing tenant's display name and subdomain. Deliberately
 * narrow: the Postgres schema name and every other piece of tenant state
 * are left untouched.
 */
final class UpdateTenant
{
    public function __construct(
        private readonly TenantSchemaContextInterface $schemaContext,
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {
    }

    public function __invoke(string $currentSubdomain, string $newName, string $newSlug): Tenant
    {
        $newTenantName = new TenantName($newSlug);

        // Defensive reset, same reasoning as ProvisionTenant: never assume
        // the connection is already on `public`.
        $this->schemaContext->resetToPublic();

        $tenant = $this->tenantRepository->findBySubdomain($currentSubdomain);
        if ($tenant === null) {
            throw TenantNotFoundException::forSubdomain($currentSubdomain);
        }

        $newSubdomain = $newTenantName->asSubdomain();

        if ($newSubdomain !== $tenant->getSubdomain()) {
            $existing = $this->tenantRepository->findBySubdomain($newSubdomain);
            if ($existing !== null) {
                throw TenantAlreadyExistsException::forSubdomain($newSubdomain);
            }
        }

        $tenant->updateDetails($newName, $newSubdomain);
        $this->tenantRepository->update($tenant);

        return $tenant;
    }
}
