<?php

namespace App\Tenancy\Domain\Exception;

use App\Shared\Domain\Exception\HasErrorKey;

/**
 * Raised when the Postgres schema derived from a tenant name already exists
 * on the database, even though no tenant is registered for it (e.g. a
 * leftover from a failed provisioning run, or a schema created outside of
 * `tenant:provision`). Provisioning refuses to create tenant data on top of
 * a schema it doesn't recognize, rather than silently reusing it.
 */
final class SchemaAlreadyExistsException extends \RuntimeException implements HasErrorKey
{
    public static function forSchemaName(string $schemaName): self
    {
        return new self(sprintf(
            'Postgres schema "%s" already exists but is not registered as a tenant. Refusing to provision over it.',
            $schemaName,
        ));
    }

    public function getErrorKey(): string
    {
        return 'tenants.error.schemaConflict';
    }

    /** @return array<string, scalar> */
    public function getErrorKeyParams(): array
    {
        return [];
    }
}
