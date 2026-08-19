<?php

namespace App\Tenancy\Domain\Exception;

/**
 * Raised when provisioning is attempted for a subdomain or schema name that
 * is already registered in the public `tenants` table.
 */
final class TenantAlreadyExistsException extends \RuntimeException
{
    public static function forSubdomain(string $subdomain): self
    {
        return new self(sprintf('A tenant with subdomain "%s" already exists.', $subdomain));
    }

    public static function forSchemaName(string $schemaName): self
    {
        return new self(sprintf('A tenant with schema "%s" already exists.', $schemaName));
    }
}
