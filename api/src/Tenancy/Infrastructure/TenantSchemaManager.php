<?php

namespace App\Tenancy\Infrastructure;

use Doctrine\DBAL\Connection;

/**
 * Low-level Postgres schema operations (CREATE/DROP SCHEMA, existence
 * check). Callers are responsible for passing an already-validated schema
 * name (see App\Tenancy\Domain\TenantName) — this class quotes the
 * identifier defensively but is not itself the whitelist boundary.
 */
final class TenantSchemaManager
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function exists(string $schemaName): bool
    {
        $found = $this->connection->fetchOne(
            'SELECT 1 FROM information_schema.schemata WHERE schema_name = :schemaName',
            ['schemaName' => $schemaName],
        );

        return $found !== false;
    }

    public function create(string $schemaName): void
    {
        $this->connection->executeStatement(sprintf(
            'CREATE SCHEMA %s',
            $this->connection->quoteSingleIdentifier($schemaName),
        ));
    }

    /**
     * Drops the schema and everything in it. Used to clean up after a
     * provisioning run fails partway through, so a failed `tenant:provision`
     * never leaves a half-migrated schema behind.
     */
    public function drop(string $schemaName): void
    {
        $this->connection->executeStatement(sprintf(
            'DROP SCHEMA IF EXISTS %s CASCADE',
            $this->connection->quoteSingleIdentifier($schemaName),
        ));
    }
}
