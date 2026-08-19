<?php

namespace App\Tenancy\Domain;

interface TenantRepositoryInterface
{
    /**
     * Looks up a tenant by its subdomain. Must always query the public
     * schema, regardless of what schema the connection's search_path is
     * currently pointed at.
     */
    public function findBySubdomain(string $subdomain): ?Tenant;
}
