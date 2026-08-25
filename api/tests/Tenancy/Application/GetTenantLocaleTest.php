<?php

namespace App\Tests\Tenancy\Application;

use App\Tenancy\Application\GetTenantLocale;
use App\Tenancy\Domain\Tenant;
use PHPUnit\Framework\TestCase;

/**
 * KOZ-34: trivial query handler — reads the already-resolved tenant's
 * default locale, no repository access needed (the Tenant aggregate is
 * already loaded by TenantResolverListener). Exists as its own
 * Application-layer class for the same reason GetTenantLoginImage does:
 * GetTenantLocaleController (the public, pre-auth /api/tenant-locale
 * endpoint the login screen calls) stays a thin HTTP translation, per this
 * project's one-query-per-use-case convention.
 */
final class GetTenantLocaleTest extends TestCase
{
    public function testReturnsTheTenantsDefaultLocale(): void
    {
        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme', defaultLocale: 'en');

        $handler = new GetTenantLocale();

        self::assertSame('en', $handler($tenant));
    }
}
