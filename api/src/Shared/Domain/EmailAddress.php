<?php

namespace App\Shared\Domain;

use App\Shared\Domain\Exception\ValidationException;

/**
 * The trim + FILTER_VALIDATE_EMAIL check the three account-creating use
 * cases each inlined. The English message and errorKey stay caller-supplied
 * because each context reports its own key (users.error.emailInvalid /
 * tenants.error.userEmailInvalid / tenants.error.adminEmailInvalid) — the
 * check is shared, the contract per endpoint is not.
 */
final class EmailAddress
{
    public static function validated(string $raw, string $englishMessage, string $errorKey): string
    {
        $email = trim($raw);

        if ($email === '' || filter_var($email, \FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::create($englishMessage, $errorKey);
        }

        return $email;
    }
}
