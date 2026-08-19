<?php

namespace App\SuperAdmin\Application;

/**
 * Read-model DTO for the super-admin tenant list: exactly the two fields
 * the DoD calls for (subdomain + aanmaakdatum), nothing from `Tenant`'s
 * internals such as the Postgres schema name leaks through this boundary.
 */
final class TenantSummary
{
    public function __construct(
        public readonly string $subdomain,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }
}
