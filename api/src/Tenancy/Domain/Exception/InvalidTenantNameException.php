<?php

namespace App\Tenancy\Domain\Exception;

final class InvalidTenantNameException extends \InvalidArgumentException
{
    public static function forName(string $name): self
    {
        return new self(sprintf(
            'Tenant name "%s" is invalid: it must be 1-55 characters, lowercase alphanumeric segments '
            . 'separated by single hyphens (e.g. "acme", "acme-bv"), with no other characters.',
            $name,
        ));
    }
}
