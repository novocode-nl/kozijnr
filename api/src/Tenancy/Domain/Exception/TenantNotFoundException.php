<?php

namespace App\Tenancy\Domain\Exception;

/**
 * Raised when an operation targets a subdomain that has no registered
 * tenant — e.g. editing a tenant that was renamed or removed by another
 * request in between.
 */
final class TenantNotFoundException extends \RuntimeException
{
    public static function forSubdomain(string $subdomain): self
    {
        return new self(sprintf('No tenant with subdomain "%s" was found.', $subdomain));
    }
}
