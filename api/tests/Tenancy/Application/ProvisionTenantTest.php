<?php

namespace App\Tests\Tenancy\Application;

use App\Tenancy\Application\ProvisionTenant;
use App\Tenancy\Domain\TenantRepositoryInterface;
use App\Tenancy\Infrastructure\TenantSchemaManager;
use App\Tenancy\Infrastructure\TenantSchemaMigrator;
use App\Tests\Tenancy\Fixtures\BrokenTenantMigrations\Version20260819070000;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Functional test for `App\Tenancy\Application\ProvisionTenant`, against
 * the real Postgres test database. Complements ProvisionTenantCommandTest,
 * which only covers failures *before* `CREATE SCHEMA` runs. This proves
 * ProvisionTenant "fails cleanly with no half-provisioned state" for the
 * one path those tests can't reach: a migration failing *after* the schema
 * has already been created.
 *
 * Uses a real, deliberately-broken tenant migration (see
 * tests/Tenancy/Fixtures/BrokenTenantMigrations/) that fails with a genuine
 * Postgres error raised mid-transaction, rather than a PHP-level exception
 * thrown before any query runs. That distinction matters: Postgres refuses
 * every statement on a connection once a transaction inside it has errored
 * ("current transaction is aborted"), until that transaction is rolled
 * back. If TenantSchemaMigrator ever left the shared Connection in that
 * state, the subsequent `DROP SCHEMA` cleanup in ProvisionTenant would
 * itself fail, silently orphaning the schema and masking the original
 * migration error. This test proves that doesn't happen: the schema is
 * actually gone afterwards, and the connection is left in a usable state.
 */
final class ProvisionTenantTest extends KernelTestCase
{
    private const SCHEMA_NAME = 'tenant_acme_bv';

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $this->resetDatabase();
    }

    protected function tearDown(): void
    {
        $this->resetDatabase();

        parent::tearDown();
    }

    public function testAFailedMigrationLeavesNoOrphanedSchemaBehind(): void
    {
        $provisionTenant = $this->provisionTenantWithBrokenMigrator();

        $thrown = null;

        try {
            $provisionTenant('acme-bv');
        } catch (\Throwable $exception) {
            $thrown = $exception;
        }

        self::assertNotNull($thrown, 'Expected the broken migration to make provisioning fail.');

        // The schema created just before the migration ran must be gone
        // again, not left behind half-migrated.
        self::assertFalse(
            $this->schemaExists(self::SCHEMA_NAME),
            'A failed migration must not leave an orphaned tenant schema behind.',
        );

        // Nothing should have been registered either.
        self::assertCount(0, $this->connection()->fetchAllAssociative('SELECT * FROM public.tenants'));

        // The cleanup (DROP SCHEMA) must have actually succeeded on the
        // shared connection rather than silently failing because the
        // connection was still stuck in an aborted-transaction state. Prove
        // the connection is healthy and usable by running a further query
        // on it.
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT 1'));
        self::assertSame('public', $this->connection()->fetchOne('SELECT current_schema()'));
    }

    private function provisionTenantWithBrokenMigrator(): ProvisionTenant
    {
        $container = self::getContainer();

        $brokenMigrator = new TenantSchemaMigrator(
            $container->get(Connection::class),
            \dirname((new \ReflectionClass(Version20260819070000::class))->getFileName()),
            'App\\Tests\\Tenancy\\Fixtures\\BrokenTenantMigrations',
        );

        return new ProvisionTenant(
            $container->get(Connection::class),
            $container->get(TenantRepositoryInterface::class),
            $container->get(TenantSchemaManager::class),
            $brokenMigrator,
        );
    }

    private function schemaExists(string $schemaName): bool
    {
        return $this->connection()->fetchOne(
            'SELECT 1 FROM information_schema.schemata WHERE schema_name = :schemaName',
            ['schemaName' => $schemaName],
        ) !== false;
    }

    private function resetDatabase(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $connection->executeStatement('DROP SCHEMA IF EXISTS ' . self::SCHEMA_NAME . ' CASCADE');
        $connection->executeStatement('DELETE FROM public.tenants');
    }

    private function connection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }
}
