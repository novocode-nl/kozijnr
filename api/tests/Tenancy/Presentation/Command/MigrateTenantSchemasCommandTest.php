<?php

namespace App\Tests\Tenancy\Presentation\Command;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Functional test for `bin/console tenant:migrate --all`, against the real
 * Postgres test database. Proves the command brings every registered
 * tenant schema up to the latest tenant migration version, and that one
 * tenant's failure doesn't stop the others from being migrated.
 */
final class MigrateTenantSchemasCommandTest extends KernelTestCase
{
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

    public function testMigratesEveryRegisteredTenantSchemaToTheLatestTenantMigrationVersion(): void
    {
        // Simulate two tenants provisioned before the baseline tenant
        // migration existed: their schemas exist and are registered, but
        // have not run any tenant migrations yet.
        $this->registerBareTenant('tenant-a', 'tenant_a');
        $this->registerBareTenant('tenant-b', 'tenant_b');

        $exitCode = $this->runMigrateAll();

        self::assertSame(0, $exitCode);
        self::assertTrue($this->hasTable('tenant_a', 'tenant_provisioning_marker'));
        self::assertTrue($this->hasTable('tenant_b', 'tenant_provisioning_marker'));
    }

    public function testRunningItAgainOnAnAlreadyMigratedSchemaIsANoOpAndStillSucceeds(): void
    {
        $this->registerBareTenant('tenant-a', 'tenant_a');
        $this->runMigrateAll();

        $exitCode = $this->runMigrateAll();

        self::assertSame(0, $exitCode);
        self::assertTrue($this->hasTable('tenant_a', 'tenant_provisioning_marker'));
    }

    public function testOneTenantFailingToMigrateDoesNotStopTheOthersFromBeingMigrated(): void
    {
        $this->registerBareTenant('tenant-a', 'tenant_a');
        // "tenant_b" is registered but its schema was never actually
        // created — migrating it must fail without blocking tenant_a.
        $this->registerTenantRowOnly('tenant-b', 'tenant_b');

        $exitCode = $this->runMigrateAll();

        self::assertNotSame(0, $exitCode);
        self::assertTrue($this->hasTable('tenant_a', 'tenant_provisioning_marker'));
    }

    public function testWithoutTheAllFlagItFailsWithoutMigratingAnything(): void
    {
        $this->registerBareTenant('tenant-a', 'tenant_a');

        $application = new Application(self::$kernel);
        $command = $application->find('tenant:migrate');
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        self::assertNotSame(0, $exitCode);
        self::assertFalse($this->hasTable('tenant_a', 'tenant_provisioning_marker'));
    }

    private function runMigrateAll(): int
    {
        $application = new Application(self::$kernel);
        $command = $application->find('tenant:migrate');
        $tester = new CommandTester($command);

        return $tester->execute(['--all' => true]);
    }

    private function registerBareTenant(string $subdomain, string $schemaName): void
    {
        $connection = $this->connection();
        $connection->executeStatement(sprintf('CREATE SCHEMA %s', $schemaName));
        $connection->executeStatement(
            'INSERT INTO public.tenants (name, subdomain, schema_name, created_at) VALUES (:subdomain, :subdomain, :schemaName, NOW())',
            ['subdomain' => $subdomain, 'schemaName' => $schemaName],
        );
    }

    private function registerTenantRowOnly(string $subdomain, string $schemaName): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO public.tenants (name, subdomain, schema_name, created_at) VALUES (:subdomain, :subdomain, :schemaName, NOW())',
            ['subdomain' => $subdomain, 'schemaName' => $schemaName],
        );
    }

    private function hasTable(string $schemaName, string $tableName): bool
    {
        return $this->connection()->fetchOne(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = :schema AND table_name = :table',
            ['schema' => $schemaName, 'table' => $tableName],
        ) !== false;
    }

    private function resetDatabase(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $connection->executeStatement('DROP SCHEMA IF EXISTS tenant_a CASCADE');
        $connection->executeStatement('DROP SCHEMA IF EXISTS tenant_b CASCADE');
        $connection->executeStatement('DELETE FROM public.tenants');
    }

    private function connection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }
}
