<?php

namespace App\Tests\Shared\Infrastructure\Security;

use App\Shared\Infrastructure\Security\SymfonyPasswordHasher;
use App\TenantUser\Domain\TenantUser;
use App\User\Domain\Role;
use App\User\Domain\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The one shared adapter behind Shared\Domain\Security\PasswordHasherInterface,
 * proven generic by exercising it with a real entity from each realm (User
 * and TenantUser both implement PasswordAuthenticatedUserInterface).
 */
final class SymfonyPasswordHasherTest extends TestCase
{
    public function testDelegatesHashingToTheSymfonyPasswordHasherForAUser(): void
    {
        $user = new User('admin@kozijnr.nl', 'placeholder', [new Role('ROLE_SUPER_ADMIN')]);

        $symfonyHasher = $this->createMock(UserPasswordHasherInterface::class);
        $symfonyHasher->expects(self::once())
            ->method('hashPassword')
            ->with($user, 'secret123')
            ->willReturn('hashed:secret123');

        $hasher = new SymfonyPasswordHasher($symfonyHasher);

        self::assertSame('hashed:secret123', $hasher->hash($user, 'secret123'));
    }

    public function testDelegatesHashingToTheSymfonyPasswordHasherForATenantUser(): void
    {
        $user = new TenantUser('tenant@kozijnr.nl', 'placeholder');

        $symfonyHasher = $this->createMock(UserPasswordHasherInterface::class);
        $symfonyHasher->expects(self::once())
            ->method('hashPassword')
            ->with($user, 'secret123')
            ->willReturn('hashed:secret123');

        $hasher = new SymfonyPasswordHasher($symfonyHasher);

        self::assertSame('hashed:secret123', $hasher->hash($user, 'secret123'));
    }

    public function testDelegatesVerificationToTheSymfonyPasswordHasher(): void
    {
        $user = new TenantUser('tenant@kozijnr.nl', 'hashed:secret123');

        $symfonyHasher = $this->createMock(UserPasswordHasherInterface::class);
        $symfonyHasher->expects(self::once())
            ->method('isPasswordValid')
            ->with($user, 'secret123')
            ->willReturn(true);

        $hasher = new SymfonyPasswordHasher($symfonyHasher);

        self::assertTrue($hasher->verify($user, 'secret123'));
    }
}
