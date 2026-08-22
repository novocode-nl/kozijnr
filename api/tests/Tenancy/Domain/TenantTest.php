<?php

namespace App\Tests\Tenancy\Domain;

use App\Tenancy\Domain\Tenant;
use PHPUnit\Framework\TestCase;

final class TenantTest extends TestCase
{
    public function testExposesNameSubdomainAndSchemaName(): void
    {
        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme');

        self::assertSame('Acme B.V.', $tenant->getName());
        self::assertSame('acme', $tenant->getSubdomain());
        self::assertSame('tenant_acme', $tenant->getSchemaName());
    }

    public function testDefaultsCreatedAtToNowWhenNotGiven(): void
    {
        $before = new \DateTimeImmutable();
        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme');
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $tenant->getCreatedAt());
        self::assertLessThanOrEqual($after, $tenant->getCreatedAt());
    }

    public function testExposesAnExplicitlyGivenCreatedAt(): void
    {
        $createdAt = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');

        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme', $createdAt);

        self::assertSame($createdAt, $tenant->getCreatedAt());
    }

    public function testRejectsEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Tenant('', 'acme', 'tenant_acme');
    }

    public function testRejectsEmptySubdomain(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Tenant('Acme B.V.', '', 'tenant_acme');
    }

    public function testRejectsEmptySchemaName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Tenant('Acme B.V.', 'acme', '');
    }

    public function testUpdateDetailsChangesNameAndSubdomainButNotTheSchemaName(): void
    {
        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme');

        $tenant->updateDetails('Acme Holding', 'acme-bv');

        self::assertSame('Acme Holding', $tenant->getName());
        self::assertSame('acme-bv', $tenant->getSubdomain());
        self::assertSame('tenant_acme', $tenant->getSchemaName());
    }

    public function testUpdateDetailsRejectsAnEmptyName(): void
    {
        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme');

        $this->expectException(\InvalidArgumentException::class);

        $tenant->updateDetails('   ', 'acme-bv');
    }

    public function testUpdateDetailsRejectsAnEmptySubdomain(): void
    {
        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme');

        $this->expectException(\InvalidArgumentException::class);

        $tenant->updateDetails('Acme Holding', '   ');
    }

    public function testIsNotArchivedByDefault(): void
    {
        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme');

        self::assertFalse($tenant->isArchived());
        self::assertNull($tenant->getArchivedAt());
    }

    public function testArchiveMarksTheTenantAsArchived(): void
    {
        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme');

        $tenant->archive();

        self::assertTrue($tenant->isArchived());
        self::assertNotNull($tenant->getArchivedAt());
    }

    public function testArchivingAnAlreadyArchivedTenantIsANoOp(): void
    {
        $archivedAt = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme', archivedAt: $archivedAt);

        $tenant->archive();

        self::assertSame($archivedAt, $tenant->getArchivedAt());
    }

    public function testUnarchiveClearsTheArchivedAt(): void
    {
        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme', archivedAt: new \DateTimeImmutable());

        $tenant->unarchive();

        self::assertFalse($tenant->isArchived());
        self::assertNull($tenant->getArchivedAt());
    }
}
