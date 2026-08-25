<?php

namespace App\Shared\Infrastructure\Security;

use App\Shared\Domain\Security\PasswordHasherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * Wraps Symfony's UserPasswordHasherInterface behind the shared
 * PasswordHasherInterface port, so Application never imports a Symfony
 * class directly.
 */
final class SymfonyPasswordHasher implements PasswordHasherInterface
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function hash(PasswordAuthenticatedUserInterface $user, string $plainPassword): string
    {
        return $this->passwordHasher->hashPassword($user, $plainPassword);
    }

    public function verify(PasswordAuthenticatedUserInterface $user, string $plainPassword): bool
    {
        return $this->passwordHasher->isPasswordValid($user, $plainPassword);
    }
}
