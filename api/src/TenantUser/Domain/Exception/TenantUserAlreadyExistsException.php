<?php

namespace App\TenantUser\Domain\Exception;

use App\Shared\Domain\Exception\HasErrorKey;

final class TenantUserAlreadyExistsException extends \RuntimeException implements HasErrorKey
{
    private string $email;

    public static function forEmail(string $email): self
    {
        $exception = new self(sprintf('A tenant user with email "%s" already exists.', $email));
        $exception->email = $email;

        return $exception;
    }

    public function getErrorKey(): string
    {
        return 'tenants.error.adminEmailAlreadyExists';
    }

    /** @return array<string, scalar> */
    public function getErrorKeyParams(): array
    {
        return ['email' => $this->email];
    }
}
