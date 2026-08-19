<?php

namespace App\Tests\User\Domain;

use App\User\Domain\Permission;
use PHPUnit\Framework\TestCase;

final class PermissionTest extends TestCase
{
    public function testExposesItsName(): void
    {
        $permission = new Permission('tenant:list');

        self::assertSame('tenant:list', $permission->getName());
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Permission('');
    }

    public function testTrimsWhitespaceFromTheName(): void
    {
        $permission = new Permission('  tenant:list  ');

        self::assertSame('tenant:list', $permission->getName());
    }
}
