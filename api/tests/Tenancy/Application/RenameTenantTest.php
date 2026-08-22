<?php

namespace App\Tests\Tenancy\Application;

use App\Tenancy\Application\RenameTenant;
use App\Tenancy\Domain\Exception\InvalidTenantNameException;
use App\Tenancy\Domain\Exception\TenantAlreadyExistsException;
use App\Tenancy\Domain\Exception\TenantNotFoundException;
use App\Tenancy\Domain\Tenant;
use App\Tenancy\Domain\TenantRepositoryInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class RenameTenantTest extends TestCase
{
    public function testRenamesAndPersistsTheTenant(): void
    {
        $tenant = new Tenant('acme', 'tenant_acme');

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('executeStatement')->with('SET search_path TO public');

        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->expects(self::exactly(2))->method('findBySubdomain')
            ->willReturnMap([
                ['acme', $tenant],
                ['acme-bv', null],
            ]);
        $repository->expects(self::once())->method('update')->with($tenant);

        $renameTenant = new RenameTenant($connection, $repository);
        $result = $renameTenant('acme', 'acme-bv');

        self::assertSame('acme-bv', $result->getSubdomain());
    }

    public function testRenamingToTheSameSubdomainIsANoOpAndSkipsTheUniquenessCheck(): void
    {
        $tenant = new Tenant('acme', 'tenant_acme');

        $connection = $this->createMock(Connection::class);
        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->expects(self::once())->method('findBySubdomain')->with('acme')->willReturn($tenant);
        $repository->expects(self::once())->method('update')->with($tenant);

        $renameTenant = new RenameTenant($connection, $repository);
        $result = $renameTenant('acme', 'acme');

        self::assertSame('acme', $result->getSubdomain());
    }

    public function testThrowsWhenTheCurrentTenantDoesNotExist(): void
    {
        $connection = $this->createMock(Connection::class);
        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->method('findBySubdomain')->with('missing')->willReturn(null);
        $repository->expects(self::never())->method('update');

        $renameTenant = new RenameTenant($connection, $repository);

        $this->expectException(TenantNotFoundException::class);
        $renameTenant('missing', 'acme-bv');
    }

    public function testThrowsWhenTheNewSubdomainIsAlreadyTaken(): void
    {
        $tenant = new Tenant('acme', 'tenant_acme');
        $other = new Tenant('beta', 'tenant_beta');

        $connection = $this->createMock(Connection::class);
        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->method('findBySubdomain')
            ->willReturnMap([
                ['acme', $tenant],
                ['beta', $other],
            ]);
        $repository->expects(self::never())->method('update');

        $renameTenant = new RenameTenant($connection, $repository);

        $this->expectException(TenantAlreadyExistsException::class);
        $renameTenant('acme', 'beta');
    }

    public function testThrowsOnAnInvalidNewName(): void
    {
        $connection = $this->createMock(Connection::class);
        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->expects(self::never())->method('findBySubdomain');
        $repository->expects(self::never())->method('update');

        $renameTenant = new RenameTenant($connection, $repository);

        $this->expectException(InvalidTenantNameException::class);
        $renameTenant('acme', 'Not Valid!');
    }
}
