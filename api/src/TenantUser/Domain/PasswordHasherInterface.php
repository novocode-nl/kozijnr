<?php

namespace App\TenantUser\Domain;

/**
 * This context's own abstraction for hashing/verifying a tenant user's
 * password, kept in Domain so Application never depends on Symfony
 * directly (same pattern as App\User\Domain\PasswordHasherInterface).
 * Infrastructure provides the real implementation by wrapping Symfony's
 * Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface.
 */
interface PasswordHasherInterface
{
    public function hash(TenantUser $user, string $plainPassword): string;

    public function verify(TenantUser $user, string $plainPassword): bool;
}
