<?php

namespace App\Tests\Tenancy\Infrastructure;

use App\Tenancy\Infrastructure\DbalTenantSchemaContext;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test for the one service that flips search_path into a tenant
 * schema and back: the switch must put the schema first (with public as
 * fallback), and the reset must happen even when the callable throws —
 * this is the reset-to-public guarantee every Application call site relies
 * on for tenant isolation.
 */
final class DbalTenantSchemaContextTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $connection->executeStatement('DROP SCHEMA IF EXISTS ctx_test CASCADE');
        $connection->executeStatement('CREATE SCHEMA ctx_test');
    }

    protected function tearDown(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $connection->executeStatement('DROP SCHEMA IF EXISTS ctx_test CASCADE');

        parent::tearDown();
    }

    public function testRunsCallableWithSchemaFirstOnSearchPathAndResetsAfterwards(): void
    {
        $context = new DbalTenantSchemaContext($this->connection());

        $searchPathInside = $context->runInSchema('ctx_test', fn () => $this->currentSearchPath());

        self::assertStringContainsString('ctx_test', $searchPathInside);
        self::assertStringContainsString('public', $searchPathInside);
        self::assertStringNotContainsString('ctx_test', $this->currentSearchPath());
    }

    public function testResetsToPublicEvenWhenTheCallableThrows(): void
    {
        $context = new DbalTenantSchemaContext($this->connection());

        try {
            $context->runInSchema('ctx_test', fn () => throw new \RuntimeException('boom'));
            self::fail('Expected the exception to propagate');
        } catch (\RuntimeException) {
        }

        self::assertStringNotContainsString('ctx_test', $this->currentSearchPath());
    }

    public function testReturnsTheCallablesReturnValue(): void
    {
        $context = new DbalTenantSchemaContext($this->connection());

        self::assertSame(42, $context->runInSchema('ctx_test', fn () => 42));
    }

    public function testResetToPublicPutsTheConnectionBackOnPublic(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO ctx_test, public');

        (new DbalTenantSchemaContext($connection))->resetToPublic();

        self::assertSame('public', $connection->fetchOne('SELECT current_schema()'));
    }

    private function currentSearchPath(): string
    {
        return (string) $this->connection()->fetchOne('SHOW search_path');
    }

    private function connection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }
}
