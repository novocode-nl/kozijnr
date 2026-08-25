<?php

namespace App\User\Application;

use App\User\Domain\User;

/**
 * Result of CreateAdminUser: the newly created admin user plus the
 * generated plain-text password. Mirrors
 * App\Tenancy\Application\ProvisionedTenantWithAdmin — the plain-text
 * password is never persisted anywhere, only its hash (already stored on
 * `$user` at this point), so this is the only place it exists outside the
 * hash. The controller must hand it back to the caller in the same
 * response; it is never retrievable afterwards.
 */
final class CreatedAdminUser
{
    public function __construct(
        public readonly User $user,
        public readonly string $password,
    ) {
    }
}
