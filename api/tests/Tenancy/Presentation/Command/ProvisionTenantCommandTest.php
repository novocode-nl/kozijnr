<?php

namespace App\Tests\Tenancy\Presentation\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Functional test for `bin/console tenant:provision <name>`, against the
 * real Postgres test database. Proves the command:
 *
 * - creates a new Postgres schema and runs the tenant migration set on it
 * - registers the tenant (subdomain + schema_name) in the public `tenants`
 *   table
 * - rejects names that don't pass the strict whitelist, including
 *   SQL-injection-shaped input, without creating anything
 * - fails cleanly (no half-provisioned schema) when the subdomain, the
 *   derived schema name, or a raw Postgres schema with that name already
 *   exists
 */
final class ProvisionTenantCommandTest extends KernelTestCase
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

    public function testProvisioningATenantCreatesItsSchemaRunsTenantMigrationsAndRegistersIt(): void
    {
        $exitCode = $this->runProvision('acme-bv');

        self::assertSame(0, $exitCode);

        $connection = $this->connection();
        self::assertSame(
            [['subdomain' => 'acme-bv', 'schema_name' => 'tenant_acme_bv']],
            $connection->fetchAllAssociative('SELECT subdomain, schema_name FROM public.tenants'),
        );

        self::assertTrue($this->schemaExists('tenant_acme_bv'));

        $tables = array_column(
            $connection->fetchAllAssociative(
                "SELECT table_name FROM information_schema.tables WHERE table_schema = 'tenant_acme_bv'"
            ),
            'table_name',
        );
        self::assertContains('tenant_provisioning_marker', $tables);
        self::assertContains('doctrine_migration_versions', $tables);
    }

    public function testProvisioningLeavesTheConnectionOnThePublicSchemaAfterwards(): void
    {
        $this->runProvision('acme-bv');

        self::assertSame('public', $this->connection()->fetchOne('SELECT current_schema()'));
    }

    /** @return iterable<string, array{0: string}> */
    public static function invalidNameProvider(): iterable
    {
        yield 'uppercase' => ['Acme'];
        yield 'sql injection attempt' => ["acme'; DROP TABLE tenants; --"];
        yield 'underscore' => ['acme_bv'];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidNameProvider')]
    public function testRejectsAnInvalidNameWithoutCreatingAnything(string $invalidName): void
    {
        $exitCode = $this->runProvision($invalidName);

        self::assertNotSame(0, $exitCode);
        self::assertCount(0, $this->connection()->fetchAllAssociative('SELECT * FROM public.tenants'));
    }

    public function testRejectsADuplicateSubdomainAndDoesNotDisturbTheExistingTenant(): void
    {
        $this->runProvision('acme-bv');

        $exitCode = $this->runProvision('acme-bv');

        self::assertNotSame(0, $exitCode);
        self::assertCount(1, $this->connection()->fetchAllAssociative('SELECT * FROM public.tenants'));
    }

    public function testRejectsProvisioningOverAPreExistingRawPostgresSchemaAndLeavesItUntouched(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('CREATE SCHEMA tenant_acme_bv');
        $connection->executeStatement('CREATE TABLE tenant_acme_bv.pre_existing (id INT PRIMARY KEY)');

        $exitCode = $this->runProvision('acme-bv');

        self::assertNotSame(0, $exitCode);
        self::assertCount(0, $connection->fetchAllAssociative('SELECT * FROM public.tenants'));

        // The pre-existing table must still be there: provisioning must not
        // have touched (let alone migrated or dropped) a schema it doesn't
        // own.
        $tables = array_column(
            $connection->fetchAllAssociative(
                "SELECT table_name FROM information_schema.tables WHERE table_schema = 'tenant_acme_bv'"
            ),
            'table_name',
        );
        self::assertSame(['pre_existing'], $tables);
    }

    private function runProvision(string $name): int
    {
        $application = new Application(self::$kernel);
        $command = $application->find('tenant:provision');
        $tester = new CommandTester($command);

        return $tester->execute(['name' => $name]);
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
        $connection->executeStatement('DROP SCHEMA IF EXISTS tenant_acme_bv CASCADE');
        $connection->executeStatement('DELETE FROM public.tenants');
    }

    private function connection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }
}
