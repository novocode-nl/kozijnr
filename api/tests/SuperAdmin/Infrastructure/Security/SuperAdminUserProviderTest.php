<?php

namespace App\Tests\SuperAdmin\Infrastructure\Security;

use App\SuperAdmin\Domain\SuperAdmin;
use App\SuperAdmin\Domain\SuperAdminRepositoryInterface;
use App\SuperAdmin\Infrastructure\Security\SuperAdminUserProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class SuperAdminUserProviderTest extends TestCase
{
    public function testLoadsAKnownSuperAdminByEmail(): void
    {
        $superAdmin = new SuperAdmin('admin@kozijnr.nl', 'hashed');
        $repository = $this->createMock(SuperAdminRepositoryInterface::class);
        $repository->expects(self::once())->method('findByEmail')->with('admin@kozijnr.nl')->willReturn($superAdmin);

        $provider = new SuperAdminUserProvider($repository);

        self::assertSame($superAdmin, $provider->loadUserByIdentifier('admin@kozijnr.nl'));
    }

    public function testThrowsWhenNoSuperAdminMatchesTheEmail(): void
    {
        $repository = $this->createMock(SuperAdminRepositoryInterface::class);
        $repository->expects(self::once())->method('findByEmail')->willReturn(null);

        $provider = new SuperAdminUserProvider($repository);

        $this->expectException(UserNotFoundException::class);

        $provider->loadUserByIdentifier('unknown@kozijnr.nl');
    }

    public function testRefreshesAUserByReLoadingItFromTheRepository(): void
    {
        $original = new SuperAdmin('admin@kozijnr.nl', 'hashed');
        $refreshed = new SuperAdmin('admin@kozijnr.nl', 'hashed');
        $repository = $this->createMock(SuperAdminRepositoryInterface::class);
        $repository->expects(self::once())->method('findByEmail')->with('admin@kozijnr.nl')->willReturn($refreshed);

        $provider = new SuperAdminUserProvider($repository);

        self::assertSame($refreshed, $provider->refreshUser($original));
    }

    public function testRejectsRefreshingAUserOfAnUnsupportedClass(): void
    {
        $repository = $this->createStub(SuperAdminRepositoryInterface::class);
        $provider = new SuperAdminUserProvider($repository);

        $this->expectException(UnsupportedUserException::class);

        $provider->refreshUser(new InMemoryUser('someone', null));
    }

    public function testSupportsOnlyTheSuperAdminClass(): void
    {
        $repository = $this->createStub(SuperAdminRepositoryInterface::class);
        $provider = new SuperAdminUserProvider($repository);

        self::assertTrue($provider->supportsClass(SuperAdmin::class));
        self::assertFalse($provider->supportsClass(InMemoryUser::class));
    }
}
