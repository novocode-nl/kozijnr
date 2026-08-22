<?php

namespace App\Tests\Shared\Infrastructure\Command;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Functional test for `bin/console app:seed-dev-fixtures` (KOZ-22): the
 * dev/test-only fixture command that seeds the standard test accounts
 * (admin@kozijnr.nl in public, tenant1 + tenant@kozijnr.nl in its schema)
 * on top of the existing `super-admin:create` (KOZ-8) and `tenant:provision`
 * (KOZ-7) use cases, so every dev/test environment (including per-worktree
 * ones) can log in with the same known credentials.
 *
 * Runs against the real Postgres test database, same as
 * CreateSuperAdminCommandTest / ProvisionTenantCommandTest.
 */
final class SeedDevFixturesCommandTest extends KernelTestCase
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

    public function testSeedsTheSuperAdminTheTenantAndTheTenantUser(): void
    {
        $exitCode = $this->runSeed();

        self::assertSame(0, $exitCode);

        $connection = $this->connection();

        $admins = $connection->fetchAllAssociative('SELECT email, password FROM public.users');
        self::assertCount(1, $admins);
        self::assertSame('admin@kozijnr.nl', $admins[0]['email']);
        self::assertNotSame('password', $admins[0]['password']);

        $tenants = $connection->fetchAllAssociative('SELECT subdomain, schema_name FROM public.tenants');
        self::assertSame([['subdomain' => 'tenant1', 'schema_name' => 'tenant_tenant1']], $tenants);

        $tenantUsers = $connection->fetchAllAssociative('SELECT email, password FROM tenant_tenant1.tenant_users');
        self::assertCount(1, $tenantUsers);
        self::assertSame('tenant@kozijnr.nl', $tenantUsers[0]['email']);
        self::assertNotSame('password', $tenantUsers[0]['password']);
    }

    public function testLeavesTheConnectionOnThePublicSchemaAfterwards(): void
    {
        $this->runSeed();

        self::assertSame('public', $this->connection()->fetchOne('SELECT current_schema()'));
    }

    public function testIsIdempotentWhenRunTwice(): void
    {
        $this->runSeed();

        $exitCode = $this->runSeed();

        self::assertSame(0, $exitCode);

        $connection = $this->connection();
        self::assertCount(1, $connection->fetchAllAssociative('SELECT * FROM public.users'));
        self::assertCount(1, $connection->fetchAllAssociative('SELECT * FROM public.tenants'));
        self::assertCount(1, $connection->fetchAllAssociative('SELECT * FROM tenant_tenant1.tenant_users'));
    }

    private function runSeed(): int
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:seed-dev-fixtures');
        $tester = new CommandTester($command);

        return $tester->execute([]);
    }

    private function connection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }

    private function resetDatabase(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $connection->executeStatement('DROP SCHEMA IF EXISTS tenant_tenant1 CASCADE');
        $connection->executeStatement('DELETE FROM public.tenants');
        $connection->executeStatement('DELETE FROM public.users');
    }
}
