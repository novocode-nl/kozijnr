<?php

namespace App\User\Domain\Exception;

/**
 * Thrown when a use case looks up a Role by name that does not exist.
 * Under normal operation this shouldn't happen for well-known roles like
 * ROLE_SUPER_ADMIN, since those are seeded by migration — seeing this means
 * the seed migration hasn't run, or the role name was misspelled.
 */
final class RoleNotFoundException extends \RuntimeException
{
    public static function forName(string $name): self
    {
        return new self(sprintf('No role found named "%s".', $name));
    }
}
