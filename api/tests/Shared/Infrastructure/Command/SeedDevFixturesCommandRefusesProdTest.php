<?php

namespace App\Tests\Shared\Infrastructure\Command;

use App\Shared\Infrastructure\Command\SeedDevFixturesCommand;
use App\Tenancy\Application\ProvisionTenant;
use App\Tenancy\Domain\TenantRepositoryInterface;
use App\TenantUser\Application\CreateTenantUser;
use App\User\Application\CreateSuperAdmin;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * KOZ-22's hard "never in production" guard: the command must refuse to run
 * (with a clear error, not a silent no-op) when it is wired up with
 * environment "prod" — regardless of what the real services would do — so a
 * fixed, publicly known password can never be seeded against a production
 * configuration.
 *
 * Constructs the command directly (rather than resolving it from the
 * container, which is always booted in the "test" environment here) with
 * environment explicitly set to "prod", using the real services from the
 * test container. If the guard were missing or came after the seeding
 * calls, this test would create real rows and fail on the assertions below.
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
        $container = self::getContainer();

        $command = new SeedDevFixturesCommand(
            $container->get(CreateSuperAdmin::class),
            $container->get(ProvisionTenant::class),
            $container->get(CreateTenantUser::class),
            $container->get(TenantRepositoryInterface::class),
            $container->get(Connection::class),
            $container->get(LoggerInterface::class),
            'prod',
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        self::assertNotSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('prod', strtolower($tester->getDisplay()));

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
