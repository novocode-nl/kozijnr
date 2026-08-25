<?php

namespace App\Tests\User\Application;

use App\Shared\Domain\Exception\ValidationException;
use App\User\Application\CreateAdminUser;
use App\User\Application\CreateSuperAdmin;
use App\User\Domain\Exception\UserAlreadyExistsException;
use App\Shared\Domain\Security\PasswordHasherInterface;
use App\User\Domain\Role;
use App\User\Domain\RoleRepositoryInterface;
use App\User\Domain\User;
use App\User\Domain\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class CreateAdminUserTest extends TestCase
{
    public function testCreatesAnAdminUserWithAGeneratedPasswordAndTheSuperAdminRole(): void
    {
        $createSuperAdmin = $this->realCreateSuperAdmin();

        $createAdminUser = new CreateAdminUser($createSuperAdmin);

        $result = $createAdminUser('new-admin@kozijnr.nl');

        self::assertSame('new-admin@kozijnr.nl', $result->user->getEmail());
        self::assertSame(['ROLE_SUPER_ADMIN'], $result->user->getRoles());
        self::assertNotEmpty($result->password);
        // 12 random bytes, hex-encoded -> 24 characters, same generation
        // scheme as ProvisionTenantWithAdmin's tenant-admin password.
        self::assertSame(24, strlen($result->password));
    }

    public function testTrimsTheEmailBeforeCreating(): void
    {
        $createSuperAdmin = $this->realCreateSuperAdmin();
        $createAdminUser = new CreateAdminUser($createSuperAdmin);

        $result = $createAdminUser('  spaced@kozijnr.nl  ');

        self::assertSame('spaced@kozijnr.nl', $result->user->getEmail());
    }

    public function testRejectsAnEmptyEmail(): void
    {
        $createAdminUser = new CreateAdminUser($this->realCreateSuperAdmin());

        try {
            $createAdminUser('');
            self::fail('Expected ValidationException.');
        } catch (ValidationException $exception) {
            self::assertSame('users.error.emailInvalid', $exception->getErrorKey());
        }
    }

    public function testRejectsAMalformedEmail(): void
    {
        $createAdminUser = new CreateAdminUser($this->realCreateSuperAdmin());

        try {
            $createAdminUser('not-an-email');
            self::fail('Expected ValidationException.');
        } catch (ValidationException $exception) {
            self::assertSame('users.error.emailInvalid', $exception->getErrorKey());
        }
    }

    public function testPropagatesADuplicateEmailAsUserAlreadyExists(): void
    {
        $existing = new User('taken@kozijnr.nl', 'hashed', [new Role('ROLE_SUPER_ADMIN')]);

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findByEmail')->willReturn($existing);

        $roleRepository = $this->createMock(RoleRepositoryInterface::class);
        $hasher = $this->createMock(PasswordHasherInterface::class);

        $createAdminUser = new CreateAdminUser(new CreateSuperAdmin($userRepository, $roleRepository, $hasher));

        $this->expectException(UserAlreadyExistsException::class);

        $createAdminUser('taken@kozijnr.nl');
    }

    private function realCreateSuperAdmin(): CreateSuperAdmin
    {
        $superAdminRole = new Role('ROLE_SUPER_ADMIN');

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findByEmail')->willReturn(null);
        $userRepository->method('add');

        $roleRepository = $this->createMock(RoleRepositoryInterface::class);
        $roleRepository->method('findByName')->with('ROLE_SUPER_ADMIN')->willReturn($superAdminRole);

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->method('hash')->willReturn('hashed-password');

        return new CreateSuperAdmin($userRepository, $roleRepository, $hasher);
    }
}
