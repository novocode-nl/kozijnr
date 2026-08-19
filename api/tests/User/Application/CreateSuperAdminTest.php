<?php

namespace App\Tests\User\Application;

use App\User\Application\CreateSuperAdmin;
use App\User\Domain\Exception\RoleNotFoundException;
use App\User\Domain\Exception\UserAlreadyExistsException;
use App\User\Domain\PasswordHasherInterface;
use App\User\Domain\Role;
use App\User\Domain\RoleRepositoryInterface;
use App\User\Domain\User;
use App\User\Domain\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class CreateSuperAdminTest extends TestCase
{
    public function testHashesThePasswordAndRegistersTheUserWithTheSuperAdminRole(): void
    {
        $superAdminRole = new Role('ROLE_SUPER_ADMIN');

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::once())->method('findByEmail')->with('admin@kozijnr.nl')->willReturn(null);
        $userRepository->expects(self::once())->method('add')->with(self::callback(
            static fn (User $user) => $user->getEmail() === 'admin@kozijnr.nl'
                && $user->getPassword() === 'hashed:secret123'
                && $user->getRoles() === ['ROLE_SUPER_ADMIN'],
        ));

        $roleRepository = $this->createMock(RoleRepositoryInterface::class);
        $roleRepository->expects(self::once())->method('findByName')->with('ROLE_SUPER_ADMIN')->willReturn($superAdminRole);

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects(self::once())
            ->method('hash')
            ->with(self::isInstanceOf(User::class), 'secret123')
            ->willReturn('hashed:secret123');

        $createSuperAdmin = new CreateSuperAdmin($userRepository, $roleRepository, $hasher);

        $user = $createSuperAdmin('admin@kozijnr.nl', 'secret123');

        self::assertSame('admin@kozijnr.nl', $user->getEmail());
        self::assertSame(['ROLE_SUPER_ADMIN'], $user->getRoles());
    }

    public function testRejectsARegisteringAnAlreadyUsedEmail(): void
    {
        $existing = new User('admin@kozijnr.nl', 'hashed', [new Role('ROLE_SUPER_ADMIN')]);
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::once())->method('findByEmail')->willReturn($existing);
        $userRepository->expects(self::never())->method('add');

        $roleRepository = $this->createMock(RoleRepositoryInterface::class);
        $roleRepository->expects(self::never())->method('findByName');

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects(self::never())->method('hash');

        $createSuperAdmin = new CreateSuperAdmin($userRepository, $roleRepository, $hasher);

        $this->expectException(UserAlreadyExistsException::class);

        $createSuperAdmin('admin@kozijnr.nl', 'secret123');
    }

    public function testThrowsWhenTheSuperAdminRoleDoesNotExist(): void
    {
        // Should never happen once the KOZ-9 seed migration has run, but
        // guards against a misconfigured/unmigrated environment rather than
        // silently creating a user with no roles.
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::once())->method('findByEmail')->willReturn(null);
        $userRepository->expects(self::never())->method('add');

        $roleRepository = $this->createMock(RoleRepositoryInterface::class);
        $roleRepository->expects(self::once())->method('findByName')->with('ROLE_SUPER_ADMIN')->willReturn(null);

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects(self::never())->method('hash');

        $createSuperAdmin = new CreateSuperAdmin($userRepository, $roleRepository, $hasher);

        $this->expectException(RoleNotFoundException::class);

        $createSuperAdmin('admin@kozijnr.nl', 'secret123');
    }
}
