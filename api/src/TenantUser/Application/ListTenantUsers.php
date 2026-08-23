<?php

namespace App\TenantUser\Application;

use App\Tenancy\Domain\Exception\TenantNotFoundException;
use App\Tenancy\Domain\TenantRepositoryInterface;
use App\TenantUser\Domain\TenantUserRepositoryInterface;
use Doctrine\DBAL\Connection;

/**
 * Read-only query for the tenant users of one tenant, used by the admin
 * "users" tab on a tenant's detail page. Looks the tenant up by subdomain
 * in the public schema, switches search_path into its tenant schema for
 * the duration of the read, then always resets back to `public` —
 * mirroring CreateTenantUserCommand's approach to the same schema switch.
 */
final class ListTenantUsers
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly TenantUserRepositoryInterface $tenantUserRepository,
    ) {
    }

    /** @return TenantUserSummary[] */
    public function __invoke(string $subdomain): array
    {
        $this->connection->executeStatement('SET search_path TO public');

        $tenant = $this->tenantRepository->findBySubdomain($subdomain);
        if ($tenant === null) {
            throw TenantNotFoundException::forSubdomain($subdomain);
        }

        $this->connection->executeStatement(sprintf(
            'SET search_path TO %s, public',
            $this->connection->quoteSingleIdentifier($tenant->getSchemaName()),
        ));

        try {
            return array_map(TenantUserSummary::fromTenantUser(...), $this->tenantUserRepository->findAll());
        } finally {
            $this->connection->executeStatement('SET search_path TO public');
        }
    }
}
