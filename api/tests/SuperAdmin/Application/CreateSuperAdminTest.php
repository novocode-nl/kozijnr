<?php

namespace App\Tests\SuperAdmin\Application;

use App\SuperAdmin\Application\CreateSuperAdmin;
use App\SuperAdmin\Domain\Exception\UserAlreadyExistsException;
use App\SuperAdmin\Domain\User;
use App\SuperAdmin\Domain\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects(self::once())
            ->method('hashPassword')
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

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects(self::never())->method('hashPassword');

        $createSuperAdmin = new CreateSuperAdmin($repository, $hasher);

        $this->expectException(UserAlreadyExistsException::class);

        $createSuperAdmin('admin@kozijnr.nl', 'secret123');
    }
}
