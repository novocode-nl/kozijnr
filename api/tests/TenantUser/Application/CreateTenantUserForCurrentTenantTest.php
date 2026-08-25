<?php

namespace App\Tests\TenantUser\Application;

use App\Shared\Domain\Exception\ValidationException;
use App\TenantUser\Application\CreateTenantUser;
use App\TenantUser\Application\CreateTenantUserForCurrentTenant;
use App\Shared\Domain\Security\PasswordHasherInterface;
use App\TenantUser\Domain\TenantUser;
use App\TenantUser\Domain\TenantUserRepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for the validation this class owns (email/role) — the
 * actual create/persist behavior is CreateTenantUser's own responsibility,
 * already covered by CreateTenantUserTest. CreateTenantUser is `final`
 * (can't be mocked directly), so a real instance is wired up here against
 * mocked repository/hasher ports instead.
 */
final class CreateTenantUserForCurrentTenantTest extends TestCase
{
    public function testCreatesAUserWithTheChosenRoleAndAGeneratedPassword(): void
    {
        $repository = $this->createMock(TenantUserRepositoryInterface::class);
        $repository->method('findByEmail')->willReturn(null);
        $repository->expects(self::once())->method('add');

        $passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $passwordHasher->method('hash')->willReturn('hashed-password');

        $createTenantUserForCurrentTenant = new CreateTenantUserForCurrentTenant(
            new CreateTenantUser($repository, $passwordHasher),
        );

        $result = $createTenantUserForCurrentTenant('collega@acme.test', TenantUser::ROLE_TENANT_ADMIN);

        self::assertSame('collega@acme.test', $result->email);
        self::assertSame([TenantUser::ROLE_TENANT_ADMIN], $result->roles);
        self::assertNotEmpty($result->password);
    }

    public function testRejectsAnInvalidEmailWithoutCallingCreateTenantUser(): void
    {
        $repository = $this->createMock(TenantUserRepositoryInterface::class);
        $repository->expects(self::never())->method('findByEmail');
        $repository->expects(self::never())->method('add');

        $passwordHasher = $this->createMock(PasswordHasherInterface::class);

        $createTenantUserForCurrentTenant = new CreateTenantUserForCurrentTenant(
            new CreateTenantUser($repository, $passwordHasher),
        );

        $this->expectException(ValidationException::class);

        $createTenantUserForCurrentTenant('not-an-email', TenantUser::DEFAULT_ROLE);
    }

    public function testRejectsARoleThatIsNotOneOfTheTwoAllowedTenantRoles(): void
    {
        $repository = $this->createMock(TenantUserRepositoryInterface::class);
        $repository->expects(self::never())->method('findByEmail');
        $repository->expects(self::never())->method('add');

        $passwordHasher = $this->createMock(PasswordHasherInterface::class);

        $createTenantUserForCurrentTenant = new CreateTenantUserForCurrentTenant(
            new CreateTenantUser($repository, $passwordHasher),
        );

        $this->expectException(ValidationException::class);

        $createTenantUserForCurrentTenant('collega@acme.test', 'ROLE_SUPER_ADMIN');
    }
}
