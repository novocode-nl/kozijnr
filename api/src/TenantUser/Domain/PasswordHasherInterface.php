<?php

namespace App\TenantUser\Domain;

/**
 * This context's own abstraction for hashing/verifying a tenant user's
 * password, kept in Domain so Application never depends on Symfony directly.
 */
interface PasswordHasherInterface
{
    public function hash(TenantUser $user, string $plainPassword): string;

    public function verify(TenantUser $user, string $plainPassword): bool;
}
