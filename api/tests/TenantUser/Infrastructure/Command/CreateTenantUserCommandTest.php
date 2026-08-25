<?php

namespace App\Tests\TenantUser\Infrastructure\Command;

use App\Tenancy\Application\ProvisionTenant;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Functional test for `bin/console tenant-user:create` against the real
 * Postgres test database: the created user must land inside the tenant's
 * own schema, and the connection must always be back on public afterwards —
 * including on the duplicate-email failure path. Added ahead of the
 * TenantSchemaContext migration so that call site has a net too.
 */
final class CreateTenantUserCommandTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $this->resetDatabase();

        // TenantName::asSchemaName() prefixes "tenant_" and replaces "-"
        // with "_", so tenant "cmd-test" lives in schema "tenant_cmd_test".
        $provisionTenant = self::getContainer()->get(ProvisionTenant::class);
        $provisionTenant('Cmd Test', 'cmd-test');
    }

    protected function tearDown(): void
    {
        $this->resetDatabase();

        parent::tearDown();
    }

    public function testCreatesTheUserInsideTheTenantsOwnSchemaAndResetsSearchPath(): void
    {
        $tester = $this->commandTester();
        $exitCode = $tester->execute(['subdomain' => 'cmd-test', 'email' => 'cli@test.nl', 'password' => 'secret']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(1, (int) $this->connection()->fetchOne(
            "SELECT COUNT(*) FROM tenant_cmd_test.tenant_users WHERE email = 'cli@test.nl'"
        ));
        self::assertStringNotContainsString('tenant_cmd_test', (string) $this->connection()->fetchOne('SHOW search_path'));
    }

    public function testFailsCleanlyAndResetsSearchPathForADuplicateEmail(): void
    {
        $tester = $this->commandTester();
        $tester->execute(['subdomain' => 'cmd-test', 'email' => 'cli@test.nl', 'password' => 'secret']);
        $exitCode = $tester->execute(['subdomain' => 'cmd-test', 'email' => 'cli@test.nl', 'password' => 'secret']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringNotContainsString('tenant_cmd_test', (string) $this->connection()->fetchOne('SHOW search_path'));
    }

    public function testFailsForAnUnknownSubdomain(): void
    {
        $tester = $this->commandTester();
        $exitCode = $tester->execute(['subdomain' => 'nope', 'email' => 'cli@test.nl', 'password' => 'secret']);

        self::assertSame(Command::FAILURE, $exitCode);
    }

    private function commandTester(): CommandTester
    {
        $application = new Application(self::$kernel);

        return new CommandTester($application->find('tenant-user:create'));
    }

    private function connection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }

    private function resetDatabase(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $connection->executeStatement('DROP SCHEMA IF EXISTS tenant_cmd_test CASCADE');
        $connection->executeStatement('DELETE FROM public.tenants');
    }
}
