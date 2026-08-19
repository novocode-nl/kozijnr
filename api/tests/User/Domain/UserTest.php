<?php

namespace App\Tests\User\Domain;

use App\User\Domain\Permission;
use App\User\Domain\Role;
use App\User\Domain\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testExposesEmailAndHashedPassword(): void
    {
        $user = new User('admin@kozijnr.nl', 'hashed-password', [new Role('ROLE_SUPER_ADMIN')]);

        self::assertSame('admin@kozijnr.nl', $user->getEmail());
        self::assertSame('hashed-password', $user->getPassword());
    }

    public function testUserIdentifierIsTheEmail(): void
    {
        $user = new User('admin@kozijnr.nl', 'hashed-password', [new Role('ROLE_SUPER_ADMIN')]);

        self::assertSame('admin@kozijnr.nl', $user->getUserIdentifier());
    }

    public function testExposesTheNamesOfItsAssignedRoles(): void
    {
        $user = new User('admin@kozijnr.nl', 'hashed-password', [new Role('ROLE_SUPER_ADMIN')]);

        self::assertSame(['ROLE_SUPER_ADMIN'], $user->getRoles());
    }

    public function testDeduplicatesRoles(): void
    {
        $role = new Role('ROLE_SUPER_ADMIN');
        $user = new User('admin@kozijnr.nl', 'hashed-password', [$role, $role]);

        self::assertSame(['ROLE_SUPER_ADMIN'], $user->getRoles());
    }

    public function testRejectsAnEmptyEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new User('', 'hashed-password', [new Role('ROLE_SUPER_ADMIN')]);
    }

    public function testRejectsAnEmptyPasswordHash(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new User('admin@kozijnr.nl', '', [new Role('ROLE_SUPER_ADMIN')]);
    }

    public function testRejectsAnEmptyRolesList(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new User('admin@kozijnr.nl', 'hashed-password', []);
    }

    public function testErasingCredentialsDoesNotAffectTheStoredPasswordHash(): void
    {
        // User only ever stores the already-hashed password, never a
        // plaintext one, so eraseCredentials() (called by Symfony Security
        // after authentication) has nothing sensitive to remove.
        $user = new User('admin@kozijnr.nl', 'hashed-password', [new Role('ROLE_SUPER_ADMIN')]);

        $user->eraseCredentials();

        self::assertSame('hashed-password', $user->getPassword());
    }

    public function testHasPermissionIsTrueWhenAnAssignedRoleGrantsIt(): void
    {
        $role = new Role('ROLE_SUPER_ADMIN');
        $role->addPermission(new Permission('tenant:list'));
        $user = new User('admin@kozijnr.nl', 'hashed-password', [$role]);

        self::assertTrue($user->hasPermission('tenant:list'));
    }

    public function testHasPermissionIsFalseWhenNoAssignedRoleGrantsIt(): void
    {
        $role = new Role('ROLE_SUPER_ADMIN');
        $role->addPermission(new Permission('tenant:list'));
        $user = new User('admin@kozijnr.nl', 'hashed-password', [$role]);

        self::assertFalse($user->hasPermission('tenant:create'));
    }

    public function testGetPermissionsFlattensAndDeduplicatesPermissionsAcrossRoles(): void
    {
        $listPermission = new Permission('tenant:list');

        $roleA = new Role('ROLE_SUPER_ADMIN');
        $roleA->addPermission($listPermission);
        $roleA->addPermission(new Permission('tenant:create'));

        $roleB = new Role('ROLE_OTHER');
        $roleB->addPermission($listPermission);

        $user = new User('admin@kozijnr.nl', 'hashed-password', [$roleA, $roleB]);

        self::assertSame(['tenant:list', 'tenant:create'], $user->getPermissions());
    }
}
