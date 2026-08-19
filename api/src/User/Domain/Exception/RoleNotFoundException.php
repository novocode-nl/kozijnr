<?php

namespace App\User\Domain\Exception;

/**
 * Thrown when a use case looks up a Role by name that does not exist in
 * the public schema's `roles` table. Under normal operation this should
 * never happen for well-known roles such as ROLE_SUPER_ADMIN, since those
 * are seeded by migration (KOZ-9) — seeing this exception means the seed
 * migration has not run, or the role name was misspelled.
 */
final class RoleNotFoundException extends \RuntimeException
{
    public static function forName(string $name): self
    {
        return new self(sprintf('No role found named "%s".', $name));
    }
}
