<?php

namespace App\Tests\SuperAdmin\Infrastructure;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Proves the KOZ-8 requirement that super-admin routes only exist on the
 * main domain: a request to a *known* tenant subdomain must 404 on
 * `/api/admin/*` exactly like an unknown route would, rather than exposing
 * (and possibly authenticating against) the super-admin API from within a
 * tenant's context.
 *
 * Deliberately reuses KOZ-6's real tenant resolution (a genuine tenant row
 * + schema) rather than mocking it, so this test also proves the guard
 * runs after TenantResolverListener has already determined the request is
 * on a tenant subdomain.
 */
final class SuperAdminRouteGuardListenerTest extends WebTestCase
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

    public function testSuperAdminLoginIsReachableOnTheMainDomain(): void
    {
        $this->client->request('POST', '/api/admin/login', server: [
            'HTTP_HOST' => self::BASE_DOMAIN,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'admin@kozijnr.nl', 'password' => 'wrong-password']));

        // Reachable — routed and authenticated against, just rejected for
        // bad credentials (401), not hidden behind a 404 the way it is on a
        // tenant subdomain.
        self::assertResponseStatusCodeSame(401);
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
