<?php

namespace App\SuperAdmin\Domain\Exception;

final class SuperAdminAlreadyExistsException extends \RuntimeException
{
    public static function forEmail(string $email): self
    {
        return new self(sprintf('A super admin with email "%s" already exists.', $email));
    }
}
