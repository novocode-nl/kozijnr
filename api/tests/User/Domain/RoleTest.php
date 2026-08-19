<?php

namespace App\Tests\User\Domain;

use App\User\Domain\Permission;
use App\User\Domain\Role;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function testExposesItsName(): void
    {
        $role = new Role('ROLE_SUPER_ADMIN');

        self::assertSame('ROLE_SUPER_ADMIN', $role->getName());
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Role('');
    }

    public function testHasNoPermissionsByDefault(): void
    {
        $role = new Role('ROLE_SUPER_ADMIN');

        self::assertSame([], $role->getPermissionNames());
        self::assertFalse($role->hasPermission('tenant:list'));
    }

    public function testAddingAPermissionMakesItGranted(): void
    {
        $role = new Role('ROLE_SUPER_ADMIN');
        $role->addPermission(new Permission('tenant:list'));

        self::assertTrue($role->hasPermission('tenant:list'));
        self::assertFalse($role->hasPermission('tenant:create'));
        self::assertSame(['tenant:list'], $role->getPermissionNames());
    }

    public function testAddingTheSamePermissionTwiceDoesNotDuplicateIt(): void
    {
        $role = new Role('ROLE_SUPER_ADMIN');
        $permission = new Permission('tenant:list');

        $role->addPermission($permission);
        $role->addPermission($permission);

        self::assertSame(['tenant:list'], $role->getPermissionNames());
    }
}
