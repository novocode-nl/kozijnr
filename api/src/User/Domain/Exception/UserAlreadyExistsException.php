<?php

namespace App\User\Domain\Exception;

use App\Shared\Domain\Exception\HasErrorKey;

final class UserAlreadyExistsException extends \RuntimeException implements HasErrorKey
{
    private string $email;

    public static function forEmail(string $email): self
    {
        $exception = new self(sprintf('A user with email "%s" already exists.', $email));
        $exception->email = $email;

        return $exception;
    }

    public function getErrorKey(): string
    {
        return 'users.error.emailAlreadyExists';
    }

    /** @return array<string, scalar> */
    public function getErrorKeyParams(): array
    {
        return ['email' => $this->email];
    }
}
