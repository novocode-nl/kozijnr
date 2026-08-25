<?php

namespace App\Tests\Tenancy\Application;

use App\Tenancy\Application\UpdateTenantDefaultLocale;
use App\Tenancy\Domain\Tenant;
use App\Tenancy\Domain\TenantRepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * KOZ-34: updates the *current* tenant's default locale — the tenant is
 * always the one already resolved from the request's subdomain
 * (App\Tenancy\Infrastructure\TenantResolverListener), never a
 * client-supplied subdomain, mirroring
 * App\TenantUser\Application\CreateTenantUserForCurrentTenant's reasoning
 * for why that's the only safe shape for a tenant-own self-service action.
 */
final class UpdateTenantDefaultLocaleTest extends TestCase
{
    public function testUpdatesAndPersistsTheTenantsDefaultLocale(): void
    {
        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme');

        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->expects(self::once())->method('update')->with($tenant);

        $handler = new UpdateTenantDefaultLocale($repository);
        $handler($tenant, 'en');

        self::assertSame('en', $tenant->getDefaultLocale());
    }

    public function testRejectsAnUnsupportedLocaleWithoutPersisting(): void
    {
        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme');

        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->expects(self::never())->method('update');

        $handler = new UpdateTenantDefaultLocale($repository);

        $this->expectException(\InvalidArgumentException::class);

        $handler($tenant, 'fr');
    }
}
