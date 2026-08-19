<?php

namespace App\Tests\SuperAdmin\Domain;

use App\SuperAdmin\Domain\SuperAdmin;
use PHPUnit\Framework\TestCase;

final class SuperAdminTest extends TestCase
{
    public function testExposesEmailAndHashedPassword(): void
    {
        $superAdmin = new SuperAdmin('admin@kozijnr.nl', 'hashed-password');

        self::assertSame('admin@kozijnr.nl', $superAdmin->getEmail());
        self::assertSame('hashed-password', $superAdmin->getPassword());
    }

    public function testUserIdentifierIsTheEmail(): void
    {
        $superAdmin = new SuperAdmin('admin@kozijnr.nl', 'hashed-password');

        self::assertSame('admin@kozijnr.nl', $superAdmin->getUserIdentifier());
    }

    public function testAlwaysHasTheSuperAdminRole(): void
    {
        $superAdmin = new SuperAdmin('admin@kozijnr.nl', 'hashed-password');

        self::assertSame(['ROLE_SUPER_ADMIN'], $superAdmin->getRoles());
    }

    public function testRejectsAnEmptyEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SuperAdmin('', 'hashed-password');
    }

    public function testRejectsAnEmptyPasswordHash(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SuperAdmin('admin@kozijnr.nl', '');
    }

    public function testErasingCredentialsDoesNotAffectTheStoredPasswordHash(): void
    {
        // SuperAdmin only ever stores the already-hashed password, never a
        // plaintext one, so eraseCredentials() (called by Symfony Security
        // after authentication) has nothing sensitive to remove.
        $superAdmin = new SuperAdmin('admin@kozijnr.nl', 'hashed-password');

        $superAdmin->eraseCredentials();

        self::assertSame('hashed-password', $superAdmin->getPassword());
    }
}
