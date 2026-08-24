<?php

namespace App\TenantUser\Domain\Exception;

use App\Shared\Domain\Exception\HasErrorKey;

/**
 * Raised for any invalid email+password combination during login —
 * deliberately the same exception and message whether the email is
 * unknown or the password is wrong, so a caller can never distinguish
 * the two.
 */
final class InvalidCredentialsException extends \RuntimeException implements HasErrorKey
{
    public static function create(): self
    {
        return new self('Invalid credentials.');
    }

    public function getErrorKey(): string
    {
        return 'auth.error.invalidCredentials';
    }

    /** @return array<string, scalar> */
    public function getErrorKeyParams(): array
    {
        return [];
    }
}
