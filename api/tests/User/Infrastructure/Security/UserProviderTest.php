<?php

namespace App\Tests\User\Infrastructure\Security;

use App\User\Domain\User;
use App\User\Domain\UserRepositoryInterface;
use App\User\Infrastructure\Security\UserProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class UserProviderTest extends TestCase
{
    public function testLoadsAKnownUserByEmail(): void
    {
        $user = new User('admin@kozijnr.nl', 'hashed', ['ROLE_SUPER_ADMIN']);
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::once())->method('findByEmail')->with('admin@kozijnr.nl')->willReturn($user);

        $provider = new UserProvider($repository);

        self::assertSame($user, $provider->loadUserByIdentifier('admin@kozijnr.nl'));
    }

    public function testThrowsWhenNoUserMatchesTheEmail(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::once())->method('findByEmail')->willReturn(null);

        $provider = new UserProvider($repository);

        $this->expectException(UserNotFoundException::class);

        $provider->loadUserByIdentifier('unknown@kozijnr.nl');
    }

    public function testRefreshesAUserByReLoadingItFromTheRepository(): void
    {
        $original = new User('admin@kozijnr.nl', 'hashed', ['ROLE_SUPER_ADMIN']);
        $refreshed = new User('admin@kozijnr.nl', 'hashed', ['ROLE_SUPER_ADMIN']);
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::once())->method('findByEmail')->with('admin@kozijnr.nl')->willReturn($refreshed);

        $provider = new UserProvider($repository);

        self::assertSame($refreshed, $provider->refreshUser($original));
    }

    public function testRejectsRefreshingAUserOfAnUnsupportedClass(): void
    {
        $repository = $this->createStub(UserRepositoryInterface::class);
        $provider = new UserProvider($repository);

        $this->expectException(UnsupportedUserException::class);

        $provider->refreshUser(new InMemoryUser('someone', null));
    }

    public function testSupportsOnlyTheUserClass(): void
    {
        $repository = $this->createStub(UserRepositoryInterface::class);
        $provider = new UserProvider($repository);

        self::assertTrue($provider->supportsClass(User::class));
        self::assertFalse($provider->supportsClass(InMemoryUser::class));
    }
}
