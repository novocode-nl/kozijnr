<?php

namespace App\Tests\User\Domain;

use App\User\Domain\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testExposesEmailAndHashedPassword(): void
    {
        $user = new User('admin@kozijnr.nl', 'hashed-password', ['ROLE_SUPER_ADMIN']);

        self::assertSame('admin@kozijnr.nl', $user->getEmail());
        self::assertSame('hashed-password', $user->getPassword());
    }

    public function testUserIdentifierIsTheEmail(): void
    {
        $user = new User('admin@kozijnr.nl', 'hashed-password', ['ROLE_SUPER_ADMIN']);

        self::assertSame('admin@kozijnr.nl', $user->getUserIdentifier());
    }

    public function testExposesTheGivenRoles(): void
    {
        $user = new User('admin@kozijnr.nl', 'hashed-password', ['ROLE_SUPER_ADMIN']);

        self::assertSame(['ROLE_SUPER_ADMIN'], $user->getRoles());
    }

    public function testDeduplicatesRoles(): void
    {
        $user = new User('admin@kozijnr.nl', 'hashed-password', ['ROLE_SUPER_ADMIN', 'ROLE_SUPER_ADMIN']);

        self::assertSame(['ROLE_SUPER_ADMIN'], $user->getRoles());
    }

    public function testRejectsAnEmptyEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new User('', 'hashed-password', ['ROLE_SUPER_ADMIN']);
    }

    public function testRejectsAnEmptyPasswordHash(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new User('admin@kozijnr.nl', '', ['ROLE_SUPER_ADMIN']);
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
        $user = new User('admin@kozijnr.nl', 'hashed-password', ['ROLE_SUPER_ADMIN']);

        $user->eraseCredentials();

        self::assertSame('hashed-password', $user->getPassword());
    }
}
