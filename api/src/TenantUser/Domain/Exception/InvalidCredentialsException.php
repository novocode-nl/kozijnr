<?php

namespace App\TenantUser\Domain\Exception;

/**
 * Raised for any invalid email+password combination during login —
 * deliberately the same exception and message whether the email is
 * unknown or the password is wrong, so a caller can never distinguish
 * the two.
 */
final class InvalidCredentialsException extends \RuntimeException
{
    public static function create(): self
    {
        return new self('Invalid credentials.');
    }
}
