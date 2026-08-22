<?php

namespace App\Tests\Tenancy\Application;

use App\Tenancy\Application\ListTenants;
use App\Tenancy\Domain\Tenant;
use App\Tenancy\Domain\TenantRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class ListTenantsTest extends TestCase
{
    public function testReturnsActiveTenantsByDefault(): void
    {
        $createdA = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $createdB = new \DateTimeImmutable('2026-02-02T11:30:00+00:00');

        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->expects(self::once())->method('findAllActive')->willReturn([
            new Tenant('Acme', 'acme', 'tenant_acme', $createdA),
            new Tenant('Beta', 'beta', 'tenant_beta', $createdB),
        ]);
        $repository->expects(self::never())->method('findAllArchived');

        $listTenants = new ListTenants($repository);
        $summaries = $listTenants();

        self::assertCount(2, $summaries);
        self::assertSame('acme', $summaries[0]->subdomain);
        self::assertSame($createdA, $summaries[0]->createdAt);
        self::assertSame('beta', $summaries[1]->subdomain);
        self::assertSame($createdB, $summaries[1]->createdAt);
    }

    public function testReturnsArchivedTenantsWhenRequested(): void
    {
        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->expects(self::once())->method('findAllArchived')->willReturn([
            new Tenant('Acme', 'acme', 'tenant_acme', archivedAt: new \DateTimeImmutable()),
        ]);
        $repository->expects(self::never())->method('findAllActive');

        $listTenants = new ListTenants($repository);
        $summaries = $listTenants(includeArchived: true);

        self::assertCount(1, $summaries);
        self::assertSame('acme', $summaries[0]->subdomain);
    }

    public function testReturnsAnEmptyListWhenNoTenantsAreRegistered(): void
    {
        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->expects(self::once())->method('findAllActive')->willReturn([]);

        $listTenants = new ListTenants($repository);

        self::assertSame([], $listTenants());
    }
}
