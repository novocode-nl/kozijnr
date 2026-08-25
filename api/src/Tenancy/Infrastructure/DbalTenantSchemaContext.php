<?php

namespace App\Tenancy\Infrastructure;

use App\Tenancy\Domain\TenantSchemaContextInterface;
use Doctrine\DBAL\Connection;

/**
 * DBAL implementation of the tenant schema switch: identical SQL to what
 * the call sites previously inlined (schema first, public as fallback,
 * quoteSingleIdentifier against injection), with the reset-to-public in a
 * finally so no code path can leave the connection pointed at a tenant
 * schema.
 */
final class DbalTenantSchemaContext implements TenantSchemaContextInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function resetToPublic(): void
    {
        $this->connection->executeStatement('SET search_path TO public');
    }

    public function runInSchema(string $schemaName, callable $fn): mixed
    {
        $this->connection->executeStatement(sprintf(
            'SET search_path TO %s, public',
            $this->connection->quoteSingleIdentifier($schemaName),
        ));

        try {
            return $fn();
        } finally {
            $this->resetToPublic();
        }
    }
}
