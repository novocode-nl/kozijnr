<?php

namespace App\TenantUser\Domain\Exception;

/**
 * Raised for any invalid email+password combination during login —
 * deliberately the same exception (and the same message) whether the email
 * is unknown or the password is wrong, so a caller can never distinguish
 * the two (KOZ-11 DoD: no "unknown email" vs. "wrong password" leak).
 */
final class InvalidCredentialsException extends \RuntimeException
{
    public static function create(): self
    {
        return new self('Invalid credentials.');
    }
}
