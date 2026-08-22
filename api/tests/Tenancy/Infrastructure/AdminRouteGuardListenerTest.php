<?php

namespace App\Tests\Tenancy\Infrastructure;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Proves that admin routes live exclusively on the reserved "admin"
 * subdomain — not a tenant subdomain, and not the bare main domain either.
 *
 * Deliberately reuses the real tenant/admin resolution (a genuine tenant
 * row + schema) rather than mocking it, so this also proves the guard runs
 * after TenantResolverListener has already determined the request kind.
 */
final class AdminRouteGuardListenerTest extends WebTestCase
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
        $connection->executeStatement('CREATE SCHEMA tenant_a');
        $connection->executeStatement('DELETE FROM public.tenants');
        $connection->executeStatement(
            "INSERT INTO public.tenants (subdomain, schema_name, created_at) VALUES ('tenant-a', 'tenant_a', NOW())"
        );
    }

    protected function tearDown(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $connection->executeStatement('DROP SCHEMA IF EXISTS tenant_a CASCADE');
        $connection->executeStatement('DELETE FROM public.tenants');

        parent::tearDown();
    }

    public function testSuperAdminLoginIsUnreachableFromAKnownTenantSubdomain(): void
    {
        $this->client->request('POST', '/api/admin/login', server: [
            'HTTP_HOST' => 'tenant-a.' . self::BASE_DOMAIN,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'admin@kozijnr.nl', 'password' => 'irrelevant']));

        self::assertResponseStatusCodeSame(404);
    }

    public function testSuperAdminTenantListIsUnreachableFromAKnownTenantSubdomain(): void
    {
        $this->client->request('GET', '/api/admin/tenants', server: [
            'HTTP_HOST' => 'tenant-a.' . self::BASE_DOMAIN,
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testSuperAdminLoginIsReachableOnTheAdminSubdomain(): void
    {
        $this->client->request('POST', '/api/admin/login', server: [
            'HTTP_HOST' => 'admin.' . self::BASE_DOMAIN,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'admin@kozijnr.nl', 'password' => 'wrong-password']));

        // Reachable — routed and authenticated against, just rejected for
        // bad credentials (401), not hidden behind a 404 the way it is on a
        // tenant subdomain or the bare main domain.
        self::assertResponseStatusCodeSame(401);
    }

    public function testSuperAdminLoginIsUnreachableOnTheBareMainDomain(): void
    {
        $this->client->request('POST', '/api/admin/login', server: [
            'HTTP_HOST' => self::BASE_DOMAIN,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'admin@kozijnr.nl', 'password' => 'wrong-password']));

        // admin.kozijnr.nl is THE place where admin business happens; the
        // bare main domain is neither a tenant nor the admin domain, so it
        // gets the same 404 treatment as any other unrecognized route.
        self::assertResponseStatusCodeSame(404);
    }

    public function testSuperAdminTenantListIsUnreachableOnTheBareMainDomain(): void
    {
        $this->client->request('GET', '/api/admin/tenants', server: [
            'HTTP_HOST' => self::BASE_DOMAIN,
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }

    public function testAdminOriginOnTheApiHostReachesAdminRoutes(): void
    {
        // The browser client on admin.<base> calls api.<base>; its Origin is
        // what makes this an admin-context request.
        $this->client->request('POST', '/api/admin/login', server: [
            'HTTP_HOST' => 'api.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://admin.' . self::BASE_DOMAIN,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'nobody@example.com', 'password' => 'wrong']));

        self::assertResponseStatusCodeSame(401);
    }

    public function testTenantOriginOnTheApiHostDoesNotReachAdminRoutes(): void
    {
        $this->client->request('GET', '/api/admin/tenants', server: [
            'HTTP_HOST' => 'api.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://tenant-a.' . self::BASE_DOMAIN,
        ]);

        self::assertResponseStatusCodeSame(404);
    }
}
