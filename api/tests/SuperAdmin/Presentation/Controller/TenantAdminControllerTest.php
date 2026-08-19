<?php

namespace App\Tests\SuperAdmin\Presentation\Controller;

use App\SuperAdmin\Application\CreateSuperAdmin;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end coverage of the KOZ-8 super-admin flow: login on the main
 * domain establishes a session, an authenticated super admin can list and
 * create tenants (which delegates to KOZ-7's real `tenant:provision`
 * machinery), and neither the API nor the session works without being
 * authenticated as a super admin first.
 */
final class TenantAdminControllerTest extends WebTestCase
{
    private const BASE_DOMAIN = 'localhost';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $connection->executeStatement('DROP SCHEMA IF EXISTS tenant_acme CASCADE');
        $connection->executeStatement('DELETE FROM public.tenants');
        $connection->executeStatement('DELETE FROM public.super_admins');

        self::getContainer()->get(CreateSuperAdmin::class)('admin@kozijnr.nl', 'super-secret-123');
    }

    protected function tearDown(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $connection->executeStatement('DROP SCHEMA IF EXISTS tenant_acme CASCADE');
        $connection->executeStatement('DELETE FROM public.tenants');
        $connection->executeStatement('DELETE FROM public.super_admins');

        parent::tearDown();
    }

    public function testUnauthenticatedRequestsToTheTenantApiAreRejected(): void
    {
        $this->client->request('GET', '/api/admin/tenants', server: ['HTTP_HOST' => self::BASE_DOMAIN]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginWithWrongPasswordFails(): void
    {
        $this->login('admin@kozijnr.nl', 'wrong-password');

        self::assertResponseStatusCodeSame(401);
    }

    public function testLoggedInSuperAdminCanListAndCreateTenants(): void
    {
        $this->login('admin@kozijnr.nl', 'super-secret-123');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/admin/tenants', server: ['HTTP_HOST' => self::BASE_DOMAIN]);
        self::assertResponseIsSuccessful();
        self::assertSame([], json_decode((string) $this->client->getResponse()->getContent(), true));

        $this->client->request('POST', '/api/admin/tenants', server: [
            'HTTP_HOST' => self::BASE_DOMAIN,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['name' => 'acme']));

        self::assertResponseStatusCodeSame(201);
        $created = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('acme', $created['subdomain']);
        self::assertArrayHasKey('createdAt', $created);

        $this->client->request('GET', '/api/admin/tenants', server: ['HTTP_HOST' => self::BASE_DOMAIN]);
        $tenants = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(1, $tenants);
        self::assertSame('acme', $tenants[0]['subdomain']);
    }

    public function testCreatingATenantWithAnInvalidNameFails(): void
    {
        $this->login('admin@kozijnr.nl', 'super-secret-123');

        $this->client->request('POST', '/api/admin/tenants', server: [
            'HTTP_HOST' => self::BASE_DOMAIN,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['name' => 'Not Valid!']));

        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->connection()->fetchAllAssociative('SELECT * FROM public.tenants'));
    }

    public function testASuperAdminSessionDoesNotWorkOnATenantSubdomain(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('CREATE SCHEMA IF NOT EXISTS tenant_acme');
        $connection->executeStatement(
            "INSERT INTO public.tenants (subdomain, schema_name, created_at) VALUES ('acme', 'tenant_acme', NOW())"
        );

        $this->login('admin@kozijnr.nl', 'super-secret-123');
        self::assertResponseIsSuccessful();

        // Replay the same authenticated session/client against the tenant
        // subdomain: the super-admin API must still be entirely
        // unreachable there (404), regardless of being logged in on the
        // main domain — a super-admin session never grants tenant access.
        $this->client->request('GET', '/api/admin/tenants', server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN]);

        self::assertResponseStatusCodeSame(404);
    }

    private function login(string $email, string $password): void
    {
        $this->client->request('POST', '/api/admin/login', server: [
            'HTTP_HOST' => self::BASE_DOMAIN,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => $email, 'password' => $password]));
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
