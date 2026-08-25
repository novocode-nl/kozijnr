<?php

namespace App\Tenancy\Application;

use App\Tenancy\Domain\Tenant;
use App\Tenancy\Domain\TenantRepositoryInterface;

/**
 * KOZ-34: command handler for a tenant-admin changing their own tenant's
 * default locale. Takes the already-resolved `Tenant` (from
 * App\Tenancy\Infrastructure\TenantResolverListener's request attribute),
 * never a client-supplied subdomain — same reasoning as
 * App\TenantUser\Application\CreateTenantUserForCurrentTenant: a tenant-own
 * self-service action must have no way to name a different tenant.
 *
 * All validation (which locales are supported) lives on `Tenant` itself
 * (Domain invariant), not here.
 */
final class UpdateTenantDefaultLocale
{
    public function __construct(private readonly TenantRepositoryInterface $tenantRepository)
    {
    }

    public function __invoke(Tenant $tenant, string $locale): void
    {
        $tenant->updateDefaultLocale($locale);
        $this->tenantRepository->update($tenant);
    }
}
