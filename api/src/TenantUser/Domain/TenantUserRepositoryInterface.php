<?php

namespace App\TenantUser\Domain;

interface TenantUserRepositoryInterface
{
    /**
     * Looks up a tenant user by email within the current tenant schema
     * (whichever schema the request's search_path is currently pointed at
     * — see App\Tenancy\Infrastructure\TenantResolverListener). Never
     * reaches across tenant schemas.
     */
    public function findByEmail(string $email): ?TenantUser;

    public function add(TenantUser $tenantUser): void;
}
