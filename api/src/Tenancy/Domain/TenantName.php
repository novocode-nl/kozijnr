<?php

namespace App\Tenancy\Domain;

use App\Tenancy\Domain\Exception\InvalidTenantNameException;

/**
 * A validated, free-text tenant name as given to `tenant:provision <name>`.
 *
 * Single choke point where a user-supplied name is checked against a strict
 * whitelist before it's ever interpolated into a raw SQL identifier
 * elsewhere in the tenancy infrastructure — guards against SQL-schema
 * injection via a crafted tenant name.
 */
final class TenantName
{
    private const PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    // Postgres identifiers are limited to 63 bytes; 55 leaves room for the
    // "tenant_" prefix added by asSchemaName().
    private const MAX_LENGTH = 55;

    private const SCHEMA_PREFIX = 'tenant_';

    private readonly string $value;

    public function __construct(string $value)
    {
        if (
            $value === ''
            || preg_match(self::PATTERN, $value) !== 1
            || mb_strlen($value) > self::MAX_LENGTH
            || $value === Subdomain::RESERVED_ADMIN
            || $value === Subdomain::RESERVED_API
        ) {
            throw InvalidTenantNameException::forName($value);
        }

        $this->value = $value;
    }

    public function asSubdomain(): string
    {
        return $this->value;
    }

    public function asSchemaName(): string
    {
        return self::SCHEMA_PREFIX . str_replace('-', '_', $this->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
