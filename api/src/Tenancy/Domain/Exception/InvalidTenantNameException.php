<?php

namespace App\Tenancy\Domain\Exception;

use App\Shared\Domain\Exception\HasErrorKey;

final class InvalidTenantNameException extends \InvalidArgumentException implements HasErrorKey
{
    private string $name;

    public static function forName(string $name): self
    {
        $exception = new self(sprintf(
            'Tenant name "%s" is invalid: it must be 1-55 characters, lowercase alphanumeric segments '
            . 'separated by single hyphens (e.g. "acme", "acme-bv"), with no other characters.',
            $name,
        ));
        $exception->name = $name;

        return $exception;
    }

    public function getErrorKey(): string
    {
        return 'form.error.tenantNameInvalid';
    }

    /** @return array<string, scalar> */
    public function getErrorKeyParams(): array
    {
        return ['name' => $this->name];
    }
}
