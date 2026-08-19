<?php

namespace App\Tests\TenantUser\Application;

use App\TenantUser\Application\CreateTenantUser;
use App\TenantUser\Domain\Exception\TenantUserAlreadyExistsException;
use App\TenantUser\Domain\PasswordHasherInterface;
use App\TenantUser\Domain\TenantUser;
use App\TenantUser\Domain\TenantUserRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class CreateTenantUserTest extends TestCase
{
    public function testCreatesAndPersistsATenantUserWithAHashedPassword(): void
    {
        $repository = $this->createMock(TenantUserRepositoryInterface::class);
        $repository->expects(self::once())->method('findByEmail')->willReturn(null);
        $repository->expects(self::once())->method('add')->with(self::callback(
            static fn (TenantUser $user): bool => $user->getEmail() === 'user@acme.test' && $user->getPassword() === 'hashed-password',
        ));

        $passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $passwordHasher->expects(self::once())->method('hash')->willReturn('hashed-password');

        $createTenantUser = new CreateTenantUser($repository, $passwordHasher);

        $user = $createTenantUser('user@acme.test', 'plain-password', ['ROLE_TENANT_ADMIN']);

        self::assertSame('user@acme.test', $user->getEmail());
        self::assertSame(['ROLE_TENANT_ADMIN'], $user->getRoles());
    }

    public function testRejectsCreatingADuplicateEmail(): void
    {
        $existing = new TenantUser('user@acme.test', 'hashed', ['ROLE_TENANT_USER']);

        $repository = $this->createMock(TenantUserRepositoryInterface::class);
        $repository->expects(self::once())->method('findByEmail')->willReturn($existing);
        $repository->expects(self::never())->method('add');

        $passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $passwordHasher->expects(self::never())->method('hash');

        $createTenantUser = new CreateTenantUser($repository, $passwordHasher);

        $this->expectException(TenantUserAlreadyExistsException::class);

        $createTenantUser('user@acme.test', 'plain-password', []);
    }
}
