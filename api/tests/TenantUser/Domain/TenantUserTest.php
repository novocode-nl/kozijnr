<?php

namespace App\Tests\TenantUser\Domain;

use App\TenantUser\Domain\TenantUser;
use PHPUnit\Framework\TestCase;

final class TenantUserTest extends TestCase
{
    public function testExposesEmailAndHashedPassword(): void
    {
        $user = new TenantUser('user@acme.test', 'hashed-password', ['ROLE_TENANT_USER']);

        self::assertSame('user@acme.test', $user->getEmail());
        self::assertSame('hashed-password', $user->getPassword());
    }

    public function testUserIdentifierIsTheEmail(): void
    {
        $user = new TenantUser('user@acme.test', 'hashed-password', ['ROLE_TENANT_USER']);

        self::assertSame('user@acme.test', $user->getUserIdentifier());
    }

    public function testExposesItsRoles(): void
    {
        $user = new TenantUser('user@acme.test', 'hashed-password', ['ROLE_TENANT_USER', 'ROLE_TENANT_ADMIN']);

        self::assertSame(['ROLE_TENANT_USER', 'ROLE_TENANT_ADMIN'], $user->getRoles());
    }

    public function testDefaultsToRoleTenantUserWhenNoRolesGiven(): void
    {
        $user = new TenantUser('user@acme.test', 'hashed-password', []);

        self::assertSame(['ROLE_TENANT_USER'], $user->getRoles());
    }

    public function testTrimsAndDeduplicatesRoles(): void
    {
        $user = new TenantUser('user@acme.test', 'hashed-password', [' ROLE_TENANT_USER ', 'ROLE_TENANT_USER']);

        self::assertSame(['ROLE_TENANT_USER'], $user->getRoles());
    }

    public function testRejectsAnEmptyEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TenantUser('', 'hashed-password', ['ROLE_TENANT_USER']);
    }

    public function testRejectsAnEmptyPasswordHash(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TenantUser('user@acme.test', '', ['ROLE_TENANT_USER']);
    }

    public function testErasingCredentialsDoesNotAffectTheStoredPasswordHash(): void
    {
        $user = new TenantUser('user@acme.test', 'hashed-password', ['ROLE_TENANT_USER']);

        $user->eraseCredentials();

        self::assertSame('hashed-password', $user->getPassword());
    }
}
