<?php

namespace App\Tests\SuperAdmin\Application;

use App\SuperAdmin\Application\CreateTenant;
use App\Tenancy\Application\ProvisionTenant;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * CreateTenant only adapts App\Tenancy\Application\ProvisionTenant (a
 * `final` class, so it cannot be doubled) to the SuperAdmin context's
 * TenantSummary read model, so this integration-tests the real service
 * against the test Postgres database rather than mocking it — same
 * approach as ProvisionTenantTest/ProvisionTenantCommandTest for KOZ-7.
 */
final class CreateTenantTest extends KernelTestCase
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

    public function testDelegatesToProvisionTenantAndReturnsATenantSummary(): void
    {
        $createTenant = new CreateTenant(self::getContainer()->get(ProvisionTenant::class));

        $summary = $createTenant('acme-bv');

        self::assertSame('acme-bv', $summary->subdomain);
        self::assertInstanceOf(\DateTimeImmutable::class, $summary->createdAt);
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
