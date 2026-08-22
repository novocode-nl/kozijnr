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

    public function testDefaultsCreatedAtToNowWhenNotGiven(): void
    {
        $before = new \DateTimeImmutable();
        $tenant = new Tenant('acme', 'tenant_acme');
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $tenant->getCreatedAt());
        self::assertLessThanOrEqual($after, $tenant->getCreatedAt());
    }

    public function testExposesAnExplicitlyGivenCreatedAt(): void
    {
        $createdAt = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');

        $tenant = new Tenant('acme', 'tenant_acme', $createdAt);

        self::assertSame($createdAt, $tenant->getCreatedAt());
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

    public function testRenameChangesTheSubdomainButNotTheSchemaName(): void
    {
        $tenant = new Tenant('acme', 'tenant_acme');

        $tenant->rename('acme-bv');

        self::assertSame('acme-bv', $tenant->getSubdomain());
        self::assertSame('tenant_acme', $tenant->getSchemaName());
    }

    public function testRenameRejectsAnEmptySubdomain(): void
    {
        $tenant = new Tenant('acme', 'tenant_acme');

        $this->expectException(\InvalidArgumentException::class);

        $tenant->rename('   ');
    }
}
