<?php

namespace App\Tests\Shared\Infrastructure\Command;

use App\Shared\Infrastructure\Command\SeedDevFixturesCommand;
use App\Tenancy\Application\ProvisionTenant;
use App\Tenancy\Domain\TenantRepositoryInterface;
use App\Tenancy\Domain\TenantSchemaContextInterface;
use App\TenantUser\Application\CreateTenantUser;
use App\User\Application\CreateSuperAdmin;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The command's hard "only ever in dev/test" guard: it must refuse to run
 * (with a clear error, not a silent no-op) unless it is wired up with
 * environment "dev" or "test" — regardless of what the real services would
 * do — so a fixed, publicly known password can never be seeded against a
 * production (or any other non-dev/test) configuration.
 *
 * This is an allowlist, not a blocklist: it must refuse "prod" AND any
 * other environment name that isn't explicitly "dev"/"test" — e.g.
 * "staging", a typo, or any future/unknown environment name — since a
 * blocklist that only checks for "prod" would let those slip through.
 *
 * Constructs the command directly (rather than resolving it from the
 * container, which is always booted in the "test" environment here) with
 * environment explicitly set to a disallowed value, using the real services
 * from the test container. If the guard were missing or came after the
 * seeding calls, this test would create real rows and fail on the
 * assertions below.
 */
final class SeedDevFixturesCommandRefusesProdTest extends KernelTestCase
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

    public function testRefusesToRunWhenEnvironmentIsProd(): void
    {
        $this->assertRefusesToRunFor('prod');
    }

    /**
     * The allowlist gap this covers: a blocklist that only rejects "prod"
     * would let "staging" (or any other non-dev/test name) through and seed
     * the fixed, publicly known password into it.
     */
    public function testRefusesToRunWhenEnvironmentIsStaging(): void
    {
        $this->assertRefusesToRunFor('staging');
    }

    private function assertRefusesToRunFor(string $environment): void
    {
        $container = self::getContainer();

        $command = new SeedDevFixturesCommand(
            $container->get(CreateSuperAdmin::class),
            $container->get(ProvisionTenant::class),
            $container->get(CreateTenantUser::class),
            $container->get(TenantRepositoryInterface::class),
            $container->get(TenantSchemaContextInterface::class),
            $container->get(LoggerInterface::class),
            $environment,
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        self::assertNotSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString(strtolower($environment), strtolower($tester->getDisplay()));

        $connection = $this->connection();
        self::assertCount(0, $connection->fetchAllAssociative('SELECT * FROM public.users'));
        self::assertCount(0, $connection->fetchAllAssociative('SELECT * FROM public.tenants'));
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
