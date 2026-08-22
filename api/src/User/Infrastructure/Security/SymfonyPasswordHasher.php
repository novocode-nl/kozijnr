<?php

namespace App\User\Infrastructure\Security;

use App\User\Domain\PasswordHasherInterface;
use App\User\Domain\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Wraps Symfony's UserPasswordHasherInterface behind this context's own
 * PasswordHasherInterface, so Application never imports a Symfony class directly.
 */
final class SymfonyPasswordHasher implements PasswordHasherInterface
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function hash(User $user, string $plainPassword): string
    {
        return $this->passwordHasher->hashPassword($user, $plainPassword);
    }
}
