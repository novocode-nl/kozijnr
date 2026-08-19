<?php

namespace App\Tests\SuperAdmin\Application;

use App\SuperAdmin\Application\CreateSuperAdmin;
use App\SuperAdmin\Domain\Exception\SuperAdminAlreadyExistsException;
use App\SuperAdmin\Domain\SuperAdmin;
use App\SuperAdmin\Domain\SuperAdminRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CreateSuperAdminTest extends TestCase
{
    public function testHashesThePasswordAndRegistersTheSuperAdmin(): void
    {
        $repository = $this->createMock(SuperAdminRepositoryInterface::class);
        $repository->expects(self::once())->method('findByEmail')->with('admin@kozijnr.nl')->willReturn(null);
        $repository->expects(self::once())->method('add')->with(self::callback(
            static fn (SuperAdmin $superAdmin) => $superAdmin->getEmail() === 'admin@kozijnr.nl'
                && $superAdmin->getPassword() === 'hashed:secret123',
        ));

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects(self::once())
            ->method('hashPassword')
            ->with(self::isInstanceOf(SuperAdmin::class), 'secret123')
            ->willReturn('hashed:secret123');

        $createSuperAdmin = new CreateSuperAdmin($repository, $hasher);

        $superAdmin = $createSuperAdmin('admin@kozijnr.nl', 'secret123');

        self::assertSame('admin@kozijnr.nl', $superAdmin->getEmail());
    }

    public function testRejectsARegisteringAnAlreadyUsedEmail(): void
    {
        $existing = new SuperAdmin('admin@kozijnr.nl', 'hashed');
        $repository = $this->createMock(SuperAdminRepositoryInterface::class);
        $repository->expects(self::once())->method('findByEmail')->willReturn($existing);
        $repository->expects(self::never())->method('add');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects(self::never())->method('hashPassword');

        $createSuperAdmin = new CreateSuperAdmin($repository, $hasher);

        $this->expectException(SuperAdminAlreadyExistsException::class);

        $createSuperAdmin('admin@kozijnr.nl', 'secret123');
    }
}
