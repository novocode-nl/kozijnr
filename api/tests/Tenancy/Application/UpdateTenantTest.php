<?php

namespace App\Tests\Tenancy\Application;

use App\Tenancy\Application\UpdateTenant;
use App\Tenancy\Domain\Exception\InvalidTenantNameException;
use App\Tenancy\Domain\Exception\TenantAlreadyExistsException;
use App\Tenancy\Domain\Exception\TenantNotFoundException;
use App\Tenancy\Domain\Tenant;
use App\Tenancy\Domain\TenantRepositoryInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class UpdateTenantTest extends TestCase
{
    public function testUpdatesAndPersistsTheTenant(): void
    {
        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme');

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('executeStatement')->with('SET search_path TO public');

        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->expects(self::exactly(2))->method('findBySubdomain')
            ->willReturnMap([
                ['acme', $tenant],
                ['acme-bv', null],
            ]);
        $repository->expects(self::once())->method('update')->with($tenant);

        $updateTenant = new UpdateTenant($connection, $repository);
        $result = $updateTenant('acme', 'Acme Holding', 'acme-bv');

        self::assertSame('Acme Holding', $result->getName());
        self::assertSame('acme-bv', $result->getSubdomain());
    }

    public function testUpdatingToTheSameSubdomainIsANoOpAndSkipsTheUniquenessCheck(): void
    {
        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme');

        $connection = $this->createMock(Connection::class);
        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->expects(self::once())->method('findBySubdomain')->with('acme')->willReturn($tenant);
        $repository->expects(self::once())->method('update')->with($tenant);

        $updateTenant = new UpdateTenant($connection, $repository);
        $result = $updateTenant('acme', 'Acme Holding', 'acme');

        self::assertSame('acme', $result->getSubdomain());
        self::assertSame('Acme Holding', $result->getName());
    }

    public function testThrowsWhenTheCurrentTenantDoesNotExist(): void
    {
        $connection = $this->createMock(Connection::class);
        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->method('findBySubdomain')->with('missing')->willReturn(null);
        $repository->expects(self::never())->method('update');

        $updateTenant = new UpdateTenant($connection, $repository);

        $this->expectException(TenantNotFoundException::class);
        $updateTenant('missing', 'Acme Holding', 'acme-bv');
    }

    public function testThrowsWhenTheNewSubdomainIsAlreadyTaken(): void
    {
        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme');
        $other = new Tenant('Beta', 'beta', 'tenant_beta');

        $connection = $this->createMock(Connection::class);
        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->method('findBySubdomain')
            ->willReturnMap([
                ['acme', $tenant],
                ['beta', $other],
            ]);
        $repository->expects(self::never())->method('update');

        $updateTenant = new UpdateTenant($connection, $repository);

        $this->expectException(TenantAlreadyExistsException::class);
        $updateTenant('acme', 'Acme Holding', 'beta');
    }

    public function testThrowsOnAnInvalidNewSlug(): void
    {
        $connection = $this->createMock(Connection::class);
        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->expects(self::never())->method('findBySubdomain');
        $repository->expects(self::never())->method('update');

        $updateTenant = new UpdateTenant($connection, $repository);

        $this->expectException(InvalidTenantNameException::class);
        $updateTenant('acme', 'Acme Holding', 'Not Valid!');
    }
}
