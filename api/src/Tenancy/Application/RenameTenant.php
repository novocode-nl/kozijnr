<?php

namespace App\Tenancy\Application;

use App\Tenancy\Domain\Exception\TenantAlreadyExistsException;
use App\Tenancy\Domain\Exception\TenantNotFoundException;
use App\Tenancy\Domain\Tenant;
use App\Tenancy\Domain\TenantName;
use App\Tenancy\Domain\TenantRepositoryInterface;
use Doctrine\DBAL\Connection;

/**
 * Renames an existing tenant's subdomain. Deliberately narrow: the
 * Postgres schema name and every other piece of tenant state are left
 * untouched — only the subdomain (the tenant's public identity) changes.
 */
final class RenameTenant
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {
    }

    public function __invoke(string $currentSubdomain, string $newName): Tenant
    {
        $newTenantName = new TenantName($newName);

        // Defensive reset, same reasoning as ProvisionTenant: never assume
        // the connection is already on `public`.
        $this->connection->executeStatement('SET search_path TO public');

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

        $tenant->rename($newSubdomain);
        $this->tenantRepository->update($tenant);

        return $tenant;
    }
}
