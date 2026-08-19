<?php

namespace App\Tests\SuperAdmin\Application;

use App\SuperAdmin\Application\ListTenants;
use App\Tenancy\Domain\Tenant;
use App\Tenancy\Domain\TenantRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class ListTenantsTest extends TestCase
{
    public function testReturnsEverySubdomainAndCreationDateFromTheTenantRepository(): void
    {
        $createdA = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $createdB = new \DateTimeImmutable('2026-02-02T11:30:00+00:00');

        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->expects(self::once())->method('findAll')->willReturn([
            new Tenant('acme', 'tenant_acme', $createdA),
            new Tenant('beta', 'tenant_beta', $createdB),
        ]);

        $listTenants = new ListTenants($repository);
        $summaries = $listTenants();

        self::assertCount(2, $summaries);
        self::assertSame('acme', $summaries[0]->subdomain);
        self::assertSame($createdA, $summaries[0]->createdAt);
        self::assertSame('beta', $summaries[1]->subdomain);
        self::assertSame($createdB, $summaries[1]->createdAt);
    }

    public function testReturnsAnEmptyListWhenNoTenantsAreRegistered(): void
    {
        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->expects(self::once())->method('findAll')->willReturn([]);

        $listTenants = new ListTenants($repository);

        self::assertSame([], $listTenants());
    }
}
