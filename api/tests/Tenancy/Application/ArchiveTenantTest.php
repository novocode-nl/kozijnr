<?php

namespace App\Tests\Tenancy\Application;

use App\Tenancy\Application\ArchiveTenant;
use App\Tenancy\Domain\Exception\TenantNotFoundException;
use App\Tenancy\Domain\Tenant;
use App\Tenancy\Domain\TenantRepositoryInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class ArchiveTenantTest extends TestCase
{
    public function testArchivesAndPersistsTheTenant(): void
    {
        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme');

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('executeStatement')->with('SET search_path TO public');

        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->expects(self::once())->method('findBySubdomain')->with('acme')->willReturn($tenant);
        $repository->expects(self::once())->method('update')->with($tenant);

        $archiveTenant = new ArchiveTenant($connection, $repository);
        $result = $archiveTenant('acme');

        self::assertTrue($result->isArchived());
    }

    public function testThrowsWhenTheTenantDoesNotExist(): void
    {
        $connection = $this->createMock(Connection::class);
        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->method('findBySubdomain')->with('missing')->willReturn(null);
        $repository->expects(self::never())->method('update');

        $archiveTenant = new ArchiveTenant($connection, $repository);

        $this->expectException(TenantNotFoundException::class);
        $archiveTenant('missing');
    }
}
