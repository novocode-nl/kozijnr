<?php

namespace App\Tests\User\Infrastructure\Security;

use App\User\Domain\User;
use App\User\Infrastructure\Security\SymfonyPasswordHasher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SymfonyPasswordHasherTest extends TestCase
{
    public function testDelegatesHashingToTheSymfonyPasswordHasher(): void
    {
        $user = new User('admin@kozijnr.nl', 'placeholder', ['ROLE_SUPER_ADMIN']);

        $symfonyHasher = $this->createMock(UserPasswordHasherInterface::class);
        $symfonyHasher->expects(self::once())
            ->method('hashPassword')
            ->with($user, 'secret123')
            ->willReturn('hashed:secret123');

        $hasher = new SymfonyPasswordHasher($symfonyHasher);

        self::assertSame('hashed:secret123', $hasher->hash($user, 'secret123'));
    }
}
