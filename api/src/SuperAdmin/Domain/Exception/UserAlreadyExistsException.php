<?php

namespace App\SuperAdmin\Domain\Exception;

final class UserAlreadyExistsException extends \RuntimeException
{
    public static function forEmail(string $email): self
    {
        return new self(sprintf('A user with email "%s" already exists.', $email));
    }
}
