<?php

namespace App\Tests\Tenancy\Presentation\Command;

use App\Tenancy\Application\ProvisionTenant;
use App\Tenancy\Domain\TenantRepositoryInterface;
use App\Tenancy\Infrastructure\TenantSchemaManager;
use App\Tenancy\Infrastructure\TenantSchemaMigrator;
use App\Tenancy\Presentation\Command\ProvisionTenantCommand;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit test for `tenant:provision`'s handling of failures that are *not*
 * one of ProvisionTenant's own domain exceptions (KOZ-7 review rework,
 * finding 3).
 *
 * `ProvisionTenant::__invoke()` (see App\Tenancy\Application\ProvisionTenant)
 * runs its existence checks and `TenantSchemaManager::create()` *before* its
 * try/catch block starts. In a real race between two concurrent
 * `tenant:provision` runs for the same name, both processes can pass the
 * `exists()` check before either has created the schema, and then the
 * second process's raw `CREATE SCHEMA` fails with a plain
 * Doctrine\DBAL\Exception rather than a `SchemaAlreadyExistsException`. This
 * test reproduces that shape directly (a raw DBAL exception from `create()`,
 * with no real Postgres involved) and proves ProvisionTenantCommand still
 * reports it via `io->error()` and Command::FAILURE, instead of letting it
 * escape as an uncaught stack trace.
 */
final class ProvisionTenantCommandUnexpectedFailureTest extends TestCase
{
    public function testARawDbalExceptionFromSchemaCreationIsReportedCleanlyInsteadOfEscaping(): void
    {
        $tenantRepository = $this->createMock(TenantRepositoryInterface::class);
        $tenantRepository->method('findBySubdomain')->willReturn(null);
        $tenantRepository->method('findBySchemaName')->willReturn(null);
        $tenantRepository->expects(self::never())->method('add');

        // A bare Doctrine\DBAL\Connection mock: quoteSingleIdentifier()
        // behaves normally (needed to build the CREATE SCHEMA statement),
        // but executeStatement() throws a raw DBAL exception for anything
        // other than the initial "SET search_path TO public" reset,
        // simulating a concurrent process having already created the same
        // schema a moment earlier.
        $connection = $this->createMock(Connection::class);
        $connection->method('quoteSingleIdentifier')->willReturnCallback(
            static fn (string $identifier): string => '"' . $identifier . '"',
        );
        $connection->method('executeStatement')->willReturnCallback(
            static function (string $sql) {
                if (str_starts_with($sql, 'SET search_path')) {
                    return 0;
                }

                throw new DriverException(
                    new class('schema "tenant_acme_bv" already exists', '42P06', 7) extends \Exception implements \Doctrine\DBAL\Driver\Exception {
                        public function getSQLState(): ?string
                        {
                            return '42P06';
                        }
                    },
                    null,
                );
            },
        );
        $connection->expects(self::never())->method('beginTransaction');

        $schemaManager = new TenantSchemaManager($connection);
        $migrator = new TenantSchemaMigrator($connection, __DIR__, 'App\\Migrations\\Tenant');

        $provisionTenant = new ProvisionTenant($connection, $tenantRepository, $schemaManager, $migrator);

        $command = new ProvisionTenantCommand($provisionTenant);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['name' => 'acme-bv']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString(
            'already exists',
            $tester->getDisplay(),
            'The raw DBAL failure must be reported via io->error(), not left as an uncaught exception.',
        );
    }
}
