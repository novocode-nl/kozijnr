<?php

namespace App\Tenancy\Domain\Exception;

use App\Shared\Domain\Exception\HasErrorKey;

/**
 * Raised when an operation targets a subdomain that has no registered
 * tenant — e.g. editing a tenant that was renamed or removed by another
 * request in between.
 */
final class TenantNotFoundException extends \RuntimeException implements HasErrorKey
{
    private string $subdomain;

    public static function forSubdomain(string $subdomain): self
    {
        $exception = new self(sprintf('No tenant with subdomain "%s" was found.', $subdomain));
        $exception->subdomain = $subdomain;

        return $exception;
    }

    public function getErrorKey(): string
    {
        return 'form.error.tenantNotFound';
    }

    /** @return array<string, scalar> */
    public function getErrorKeyParams(): array
    {
        return ['subdomain' => $this->subdomain];
    }
}
