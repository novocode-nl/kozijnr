<?php

namespace App\TenantUser\Application;

use App\TenantUser\Domain\TenantUserRepositoryInterface;

/**
 * Lists the tenant users of whichever tenant schema is *already* active on
 * the current Doctrine connection (KOZ-31 rework), mirroring
 * CreateTenantUserForCurrentTenant's split: used directly by the
 * tenant-own self-service "Gebruikers" page
 * (App\TenantUser\Infrastructure\Controller\ListOwnTenantUsersController,
 * search_path already pointed at the logged-in tenant-user's own tenant by
 * TenantResolverListener) and internally by ListTenantUsers, which
 * switches search_path itself first for the admin "Gebruikers" tab (a
 * client-supplied subdomain).
 */
final class ListTenantUsersForCurrentTenant
{
    public function __construct(private readonly TenantUserRepositoryInterface $tenantUserRepository)
    {
    }

    /** @return TenantUserSummary[] */
    public function __invoke(): array
    {
        return array_map(TenantUserSummary::fromTenantUser(...), $this->tenantUserRepository->findAll());
    }
}
