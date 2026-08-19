<?php

namespace App\TenantUser\Infrastructure\Security;

use App\TenantUser\Domain\PasswordHasherInterface;
use App\TenantUser\Domain\TenantUser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Wraps Symfony's UserPasswordHasherInterface behind this context's own
 * App\TenantUser\Domain\PasswordHasherInterface, so Symfony stays confined
 * to Infrastructure and Application never imports a Symfony class directly
 * (same pattern as App\User\Infrastructure\Security\SymfonyPasswordHasher).
 */
final class SymfonyPasswordHasher implements PasswordHasherInterface
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function hash(TenantUser $user, string $plainPassword): string
    {
        return $this->passwordHasher->hashPassword($user, $plainPassword);
    }

    public function verify(TenantUser $user, string $plainPassword): bool
    {
        return $this->passwordHasher->isPasswordValid($user, $plainPassword);
    }
}
