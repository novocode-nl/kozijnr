<?php

namespace App\Tenancy\Domain;

use App\Tenancy\Domain\Exception\InvalidTenantNameException;

/**
 * A validated, free-text tenant name as given to `tenant:provision <name>`.
 *
 * This is the single choke point where a user-supplied name is checked
 * against a strict whitelist before it is ever interpolated into a raw SQL
 * identifier (CREATE SCHEMA, SET search_path, ...) elsewhere in the tenancy
 * infrastructure. Only lowercase alphanumeric segments separated by single
 * hyphens are allowed — no quotes, semicolons, whitespace, or other
 * characters that could be used for SQL-schema injection via a crafted
 * tenant name.
 *
 * The same validated value is used both as the tenant's subdomain (as-is)
 * and, with hyphens turned into underscores and a "tenant_" prefix, as the
 * Postgres schema name — keeping the two in lockstep so there is never any
 * ambiguity about which schema a subdomain maps to.
 */
final class TenantName
{
    private const PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    // Postgres identifiers are limited to 63 bytes; 55 leaves room for the
    // "tenant_" prefix (7 chars) added by asSchemaName() while staying well
    // under that limit, matching the `subdomain`/`schema_name` column
    // lengths (63) on the `tenants` table.
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
