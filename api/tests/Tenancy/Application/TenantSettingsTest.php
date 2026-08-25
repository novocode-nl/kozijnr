<?php

namespace App\Tests\Tenancy\Application;

use App\Tenancy\Application\TenantSettings;
use App\Tenancy\Domain\Tenant;
use PHPUnit\Framework\TestCase;

final class TenantSettingsTest extends TestCase
{
    public function testFromTenantExposesTheDefaultLocaleAndWhetherALoginImageExists(): void
    {
        $withoutImage = new Tenant('Acme B.V.', 'acme', 'tenant_acme', defaultLocale: 'en');
        $withImage = new Tenant(
            'Beta',
            'beta',
            'tenant_beta',
            loginImageStorageKey: 'tenant-login-images/1/abc.png',
        );

        self::assertSame(
            ['defaultLocale' => 'en', 'hasLoginImage' => false],
            TenantSettings::fromTenant($withoutImage)->toArray(),
        );
        self::assertSame(
            ['defaultLocale' => 'nl', 'hasLoginImage' => true],
            TenantSettings::fromTenant($withImage)->toArray(),
        );
    }
}
