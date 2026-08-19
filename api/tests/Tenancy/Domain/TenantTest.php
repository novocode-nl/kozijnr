<?php

namespace App\Tests\Tenancy\Domain;

use App\Tenancy\Domain\Tenant;
use PHPUnit\Framework\TestCase;

final class TenantTest extends TestCase
{
    public function testExposesSubdomainAndSchemaName(): void
    {
        $tenant = new Tenant('acme', 'tenant_acme');

        self::assertSame('acme', $tenant->getSubdomain());
        self::assertSame('tenant_acme', $tenant->getSchemaName());
    }

    public function testRejectsEmptySubdomain(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Tenant('', 'tenant_acme');
    }

    public function testRejectsEmptySchemaName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Tenant('acme', '');
    }
}
