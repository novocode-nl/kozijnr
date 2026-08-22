<?php

namespace App\Tenancy\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigratorConfiguration;
use Psr\Log\NullLogger;

/**
 * Runs the *tenant* migration set against a single tenant schema, bringing
 * it up to the latest tenant migration version. Deliberately independent
 * of the *public* migration set and the bundle's `doctrine:migrations:migrate`
 * command, so the two migration histories never interleave. Each tenant
 * schema gets its own `doctrine_migration_versions` tracking table, created
 * by pointing search_path at the schema before building the DependencyFactory.
 */
final class TenantSchemaMigrator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $migrationsPath,
        private readonly string $migrationsNamespace = 'App\\Migrations\\Tenant',
    ) {
    }

    /**
     * @param string $schemaName Already-validated schema name (see
     *                           App\Tenancy\Domain\TenantName) — not
     *                           re-validated here.
     */
    public function migrateToLatest(string $schemaName): void
    {
        $this->connection->executeStatement(sprintf(
            'SET search_path TO %s',
            $this->connection->quoteSingleIdentifier($schemaName),
        ));

        try {
            $dependencyFactory = DependencyFactory::fromConnection(
                new ConfigurationArray([
                    'migrations_paths' => [
                        $this->migrationsNamespace => $this->migrationsPath,
                    ],
                ]),
                new ExistingConnection($this->connection),
                new NullLogger(),
            );

            $dependencyFactory->getMetadataStorage()->ensureInitialized();

            $latest = $dependencyFactory->getVersionAliasResolver()->resolveVersionAlias('latest');
            $plan = $dependencyFactory->getMigrationPlanCalculator()->getPlanUntilVersion($latest);

            $dependencyFactory->getMigrator()->migrate($plan, new MigratorConfiguration());
        } finally {
            // Always leave the connection back on `public` — provisioning
            // and repository code that runs after this assumes that.
            $this->connection->executeStatement('SET search_path TO public');
        }
    }
}
