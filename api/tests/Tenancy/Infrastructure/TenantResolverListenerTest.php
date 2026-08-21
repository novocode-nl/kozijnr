<?php

namespace App\Tests\Tenancy\Infrastructure;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integration test proving the core promise of KOZ-6: a request's subdomain
 * determines which Postgres schema its queries run against, data in one
 * tenant schema is never visible from another tenant's request, an unknown
 * subdomain 404s instead of falling back to any schema, and the main domain
 * (no subdomain) stays on the public schema.
 *
 * Uses the real Postgres test database (see docker-compose.yml / README —
 * "Testing multiple subdomains locally"), with two throwaway tenant schemas
 * created and torn down around the test.
 */
final class TenantResolverListenerTest extends WebTestCase
{
    private const BASE_DOMAIN = 'localhost';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');

        $connection->executeStatement('DROP SCHEMA IF EXISTS tenant_a CASCADE');
        $connection->executeStatement('DROP SCHEMA IF EXISTS tenant_b CASCADE');
        $connection->executeStatement('CREATE SCHEMA tenant_a');
        $connection->executeStatement('CREATE SCHEMA tenant_b');
        $connection->executeStatement('CREATE TABLE tenant_a.notes (id SERIAL PRIMARY KEY, body TEXT NOT NULL)');
        $connection->executeStatement('CREATE TABLE tenant_b.notes (id SERIAL PRIMARY KEY, body TEXT NOT NULL)');
        $connection->executeStatement("INSERT INTO tenant_a.notes (body) VALUES ('secret from tenant A')");
        $connection->executeStatement("INSERT INTO tenant_b.notes (body) VALUES ('secret from tenant B')");

        $connection->executeStatement('DELETE FROM public.tenants');
        $connection->executeStatement(
            "INSERT INTO public.tenants (subdomain, schema_name, created_at) VALUES "
            . "('tenant-a', 'tenant_a', NOW()), ('tenant-b', 'tenant_b', NOW())"
        );
    }

    protected function tearDown(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $connection->executeStatement('DROP SCHEMA IF EXISTS tenant_a CASCADE');
        $connection->executeStatement('DROP SCHEMA IF EXISTS tenant_b CASCADE');
        $connection->executeStatement('DELETE FROM public.tenants');

        parent::tearDown();
    }

    public function testRequestToTenantASubdomainOnlySeesTenantAData(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/health', server: ['HTTP_HOST' => 'tenant-a.' . self::BASE_DOMAIN]);

        self::assertResponseIsSuccessful();

        $bodies = array_column($this->connection()->fetchAllAssociative('SELECT body FROM notes'), 'body');
        self::assertSame(['secret from tenant A'], $bodies);
    }

    public function testRequestToTenantBSubdomainOnlySeesTenantBData(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/health', server: ['HTTP_HOST' => 'tenant-b.' . self::BASE_DOMAIN]);

        self::assertResponseIsSuccessful();

        $bodies = array_column($this->connection()->fetchAllAssociative('SELECT body FROM notes'), 'body');
        self::assertSame(['secret from tenant B'], $bodies);
    }

    public function testUnknownSubdomainReturns404InsteadOfFallingBackToAnySchema(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/health', server: ['HTTP_HOST' => 'does-not-exist.' . self::BASE_DOMAIN]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testMainDomainWithoutSubdomainStaysOnThePublicSchema(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/health', server: ['HTTP_HOST' => self::BASE_DOMAIN]);

        self::assertResponseIsSuccessful();

        $currentSchema = $this->connection()->fetchOne('SELECT current_schema()');
        self::assertSame('public', $currentSchema);
    }

    public function testAdminSubdomainIsReservedAndStaysOnThePublicSchemaWithoutBeingTreatedAsAnUnknownTenant(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/health', server: ['HTTP_HOST' => 'admin.' . self::BASE_DOMAIN]);

        // Not a 404: "admin" is a reserved, recognized super-admin domain,
        // not an unknown tenant subdomain, so it must not fall into the
        // "unknown tenant" 404 branch.
        self::assertResponseIsSuccessful();

        $currentSchema = $this->connection()->fetchOne('SELECT current_schema()');
        self::assertSame('public', $currentSchema);
    }

    public function testApiSubdomainIsReservedAndStaysOnThePublicSchemaWithoutBeingTreatedAsAnUnknownTenant(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/health', server: ['HTTP_HOST' => 'api.' . self::BASE_DOMAIN]);

        // Not a 404: "api" is a reserved domain (KOZ-12), the stand-in for
        // what used to be a bare no-subdomain request, not an unknown
        // tenant subdomain.
        self::assertResponseIsSuccessful();

        $currentSchema = $this->connection()->fetchOne('SELECT current_schema()');
        self::assertSame('public', $currentSchema);
    }

    public function testMultiLevelSubdomainThatIsNotAKnownTenantReturns404(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/health', server: ['HTTP_HOST' => 'a.b.' . self::BASE_DOMAIN]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testKnownTenantSubdomainWithPortInHostHeaderStillResolvesTheTenant(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/health', server: ['HTTP_HOST' => 'tenant-a.' . self::BASE_DOMAIN . ':8000']);

        self::assertResponseIsSuccessful();

        $currentSchema = $this->connection()->fetchOne('SELECT current_schema()');
        self::assertSame('tenant_a', $currentSchema);
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }

    public function testOriginHeaderSelectsTheTenantOnTheApiHost(): void
    {
        $this->client->request('GET', '/api/health', server: [
            'HTTP_HOST' => 'api.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://tenant-a.' . self::BASE_DOMAIN,
        ]);

        self::assertResponseIsSuccessful();

        $bodies = array_column($this->connection()->fetchAllAssociative('SELECT body FROM notes'), 'body');
        self::assertSame(['secret from tenant A'], $bodies);
    }

    public function testOriginWithPortSelectsTheTenantOnTheApiHost(): void
    {
        $this->client->request('GET', '/api/health', server: [
            'HTTP_HOST' => 'api.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://tenant-b.' . self::BASE_DOMAIN . ':8080',
        ]);

        self::assertResponseIsSuccessful();

        $bodies = array_column($this->connection()->fetchAllAssociative('SELECT body FROM notes'), 'body');
        self::assertSame(['secret from tenant B'], $bodies);
    }

    public function testUnknownTenantInOriginOnTheApiHostReturns404(): void
    {
        $this->client->request('GET', '/api/health', server: [
            'HTTP_HOST' => 'api.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://does-not-exist.' . self::BASE_DOMAIN,
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testOriginIsIgnoredWhenTheHostItselfIsATenantSubdomain(): void
    {
        $this->client->request('GET', '/api/health', server: [
            'HTTP_HOST' => 'tenant-b.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://tenant-a.' . self::BASE_DOMAIN,
        ]);

        self::assertResponseIsSuccessful();

        $bodies = array_column($this->connection()->fetchAllAssociative('SELECT body FROM notes'), 'body');
        self::assertSame(['secret from tenant B'], $bodies);
    }

    public function testForeignOriginOnTheApiHostStaysOnThePublicSchema(): void
    {
        $this->client->request('GET', '/api/health', server: [
            'HTTP_HOST' => 'api.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://tenant-a.localhost.evil.example',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame('public', $this->connection()->fetchOne('SELECT current_schema()'));
    }

    public function testApiOriginOnTheApiHostStaysOnThePublicSchema(): void
    {
        $this->client->request('GET', '/api/health', server: [
            'HTTP_HOST' => 'api.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://api.' . self::BASE_DOMAIN,
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame('public', $this->connection()->fetchOne('SELECT current_schema()'));
    }
}
