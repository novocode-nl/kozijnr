<?php

namespace App\TenantUser\Domain\Exception;

final class TenantUserAlreadyExistsException extends \RuntimeException
{
    public static function forEmail(string $email): self
    {
        return new self(sprintf('A tenant user with email "%s" already exists.', $email));
    }
}
