<?php

namespace App\TenantUser\Domain;

interface TenantUserRepositoryInterface
{
    /** Looks up a tenant user by email within the current tenant schema. Never reaches across tenant schemas. */
    public function findByEmail(string $email): ?TenantUser;

    /**
     * All tenant users within the current tenant schema. Never reaches
     * across tenant schemas — the caller is responsible for pointing
     * search_path at the right tenant first.
     *
     * @return TenantUser[]
     */
    public function findAll(): array;

    public function add(TenantUser $tenantUser): void;
}
