<?php

namespace App\Tests\User\Application;

use App\User\Application\CreateSuperAdmin;
use App\User\Domain\Exception\UserAlreadyExistsException;
use App\User\Domain\PasswordHasherInterface;
use App\User\Domain\User;
use App\User\Domain\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class CreateSuperAdminTest extends TestCase
{
    public function testHashesThePasswordAndRegistersTheUserWithTheSuperAdminRole(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::once())->method('findByEmail')->with('admin@kozijnr.nl')->willReturn(null);
        $repository->expects(self::once())->method('add')->with(self::callback(
            static fn (User $user) => $user->getEmail() === 'admin@kozijnr.nl'
                && $user->getPassword() === 'hashed:secret123'
                && $user->getRoles() === ['ROLE_SUPER_ADMIN'],
        ));

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects(self::once())
            ->method('hash')
            ->with(self::isInstanceOf(User::class), 'secret123')
            ->willReturn('hashed:secret123');

        $createSuperAdmin = new CreateSuperAdmin($repository, $hasher);

        $user = $createSuperAdmin('admin@kozijnr.nl', 'secret123');

        self::assertSame('admin@kozijnr.nl', $user->getEmail());
        self::assertSame(['ROLE_SUPER_ADMIN'], $user->getRoles());
    }

    public function testRejectsARegisteringAnAlreadyUsedEmail(): void
    {
        $existing = new User('admin@kozijnr.nl', 'hashed', ['ROLE_SUPER_ADMIN']);
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->expects(self::once())->method('findByEmail')->willReturn($existing);
        $repository->expects(self::never())->method('add');

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects(self::never())->method('hash');

        $createSuperAdmin = new CreateSuperAdmin($repository, $hasher);

        $this->expectException(UserAlreadyExistsException::class);

        $createSuperAdmin('admin@kozijnr.nl', 'secret123');
    }
}
