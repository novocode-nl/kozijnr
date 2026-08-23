<?php

namespace App\Tests\Tenancy\Infrastructure\Controller;

use App\User\Application\CreateSuperAdmin;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end coverage of the super-admin tenant-management flow (KOZ-24,
 * KOZ-25, KOZ-27): login on the reserved admin subdomain establishes a
 * session, an authenticated super admin can list/create/update/archive/
 * unarchive tenants and list a tenant's users, and neither works without
 * authenticating as a super admin first.
 *
 * Named for the API surface rather than a controller class: each action is
 * served by its own single-action controller class (see
 * `kozijnr-backend`'s "one action per class" rule).
 */
final class TenantAdminApiTest extends WebTestCase
{
    private const BASE_DOMAIN = 'localhost';
    private const ADMIN_HOST = 'admin.localhost';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $connection->executeStatement('DROP SCHEMA IF EXISTS tenant_acme CASCADE');
        $connection->executeStatement('DROP SCHEMA IF EXISTS tenant_acme_bv CASCADE');
        $connection->executeStatement('DROP SCHEMA IF EXISTS tenant_beta CASCADE');
        $connection->executeStatement('DELETE FROM public.tenants');
        $connection->executeStatement('DELETE FROM public.users');

        self::getContainer()->get(CreateSuperAdmin::class)('admin@kozijnr.nl', 'super-secret-123');
    }

    protected function tearDown(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $connection->executeStatement('DROP SCHEMA IF EXISTS tenant_acme CASCADE');
        $connection->executeStatement('DROP SCHEMA IF EXISTS tenant_acme_bv CASCADE');
        $connection->executeStatement('DROP SCHEMA IF EXISTS tenant_beta CASCADE');
        $connection->executeStatement('DELETE FROM public.tenants');
        $connection->executeStatement('DELETE FROM public.users');

        parent::tearDown();
    }

    public function testUnauthenticatedRequestsToTheTenantApiAreRejected(): void
    {
        $this->client->request('GET', '/api/admin/tenants', server: ['HTTP_HOST' => self::ADMIN_HOST]);

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

        $this->client->request('GET', '/api/admin/tenants', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        self::assertResponseIsSuccessful();
        self::assertSame([], json_decode((string) $this->client->getResponse()->getContent(), true));

        $created = $this->createTenant('Acme B.V.', 'acme');

        self::assertSame('Acme B.V.', $created['name']);
        self::assertSame('acme', $created['subdomain']);
        self::assertArrayHasKey('createdAt', $created);
        self::assertFalse($created['archived']);

        $this->client->request('GET', '/api/admin/tenants', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        $tenants = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(1, $tenants);
        self::assertSame('acme', $tenants[0]['subdomain']);
    }

    public function testCreatingATenantAutomaticallyCreatesATenantAdminAccount(): void
    {
        $this->login('admin@kozijnr.nl', 'super-secret-123');

        $created = $this->createTenant('Acme B.V.', 'acme', 'beheerder@acme.test');

        self::assertArrayHasKey('tenantAdmin', $created);
        self::assertSame('beheerder@acme.test', $created['tenantAdmin']['email']);
        self::assertNotEmpty($created['tenantAdmin']['password']);

        $this->client->request('GET', '/api/admin/tenants/acme/users', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        self::assertResponseIsSuccessful();
        $users = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertCount(1, $users);
        self::assertSame('beheerder@acme.test', $users[0]['email']);
        self::assertContains('ROLE_TENANT_ADMIN', $users[0]['roles']);
    }

    public function testCreatingATenantWithAnInvalidAdminEmailFails(): void
    {
        $this->login('admin@kozijnr.nl', 'super-secret-123');

        $this->client->request('POST', '/api/admin/tenants', server: [
            'HTTP_HOST' => self::ADMIN_HOST,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['name' => 'Acme', 'slug' => 'acme', 'adminEmail' => 'not-an-email']));

        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->connection()->fetchAllAssociative('SELECT * FROM public.tenants'));

        // KOZ-29: the response carries a stable, machine-readable error key
        // alongside the English fallback message, so the frontend can show
        // it translated instead of the raw English text.
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('form.error.invalidEmail', $payload['errorKey']);
    }

    public function testCreatingATenantWithAnEmptyAdminEmailFails(): void
    {
        $this->login('admin@kozijnr.nl', 'super-secret-123');

        $this->client->request('POST', '/api/admin/tenants', server: [
            'HTTP_HOST' => self::ADMIN_HOST,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['name' => 'Acme', 'slug' => 'acme', 'adminEmail' => '']));

        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->connection()->fetchAllAssociative('SELECT * FROM public.tenants'));
    }

    public function testCreatingATenantWithADuplicateSubdomainFailsWithAStableErrorKey(): void
    {
        $this->login('admin@kozijnr.nl', 'super-secret-123');

        $this->createTenant('Acme B.V.', 'acme');

        $this->client->request('POST', '/api/admin/tenants', server: [
            'HTTP_HOST' => self::ADMIN_HOST,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['name' => 'Acme Again', 'slug' => 'acme', 'adminEmail' => 'other@acme.test']));

        self::assertResponseStatusCodeSame(422);

        // KOZ-29: the same errorKey comes back regardless of what the
        // client happened to send as Accept-Language — the backend never
        // translates, it always returns this one stable key, and it's the
        // frontend's own i18n catalog (NL/EN) that renders it differently
        // depending on the user's active language. See
        // web/lib/i18n/resources/{nl,en}.json for the two renderings of
        // this exact key.
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('form.error.tenantAlreadyExistsSubdomain', $payload['errorKey']);
        self::assertSame(['subdomain' => 'acme'], $payload['errorKeyParams']);
    }

    public function testCreatingATenantNamedAdminFailsBecauseTheSubdomainIsReservedForSuperAdmin(): void
    {
        $this->login('admin@kozijnr.nl', 'super-secret-123');

        $this->client->request('POST', '/api/admin/tenants', server: [
            'HTTP_HOST' => self::ADMIN_HOST,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['name' => 'Admin', 'slug' => 'admin']));

        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->connection()->fetchAllAssociative('SELECT * FROM public.tenants'));
    }

    public function testCreatingATenantWithAnInvalidSlugFails(): void
    {
        $this->login('admin@kozijnr.nl', 'super-secret-123');

        $this->client->request('POST', '/api/admin/tenants', server: [
            'HTTP_HOST' => self::ADMIN_HOST,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['name' => 'Acme', 'slug' => 'Not Valid!']));

        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->connection()->fetchAllAssociative('SELECT * FROM public.tenants'));
    }

    public function testCreatingATenantWithAnEmptyNameFails(): void
    {
        $this->login('admin@kozijnr.nl', 'super-secret-123');

        $this->client->request('POST', '/api/admin/tenants', server: [
            'HTTP_HOST' => self::ADMIN_HOST,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['name' => '', 'slug' => 'acme']));

        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->connection()->fetchAllAssociative('SELECT * FROM public.tenants'));
    }

    public function testLoggedInSuperAdminCanUpdateATenant(): void
    {
        $this->login('admin@kozijnr.nl', 'super-secret-123');

        $this->createTenant('Acme B.V.', 'acme');

        $this->client->request('PATCH', '/api/admin/tenants/acme', server: [
            'HTTP_HOST' => self::ADMIN_HOST,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['name' => 'Acme Holding', 'slug' => 'acme-bv']));

        self::assertResponseIsSuccessful();
        $updated = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('Acme Holding', $updated['name']);
        self::assertSame('acme-bv', $updated['subdomain']);

        $this->client->request('GET', '/api/admin/tenants', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        $tenants = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(1, $tenants);
        self::assertSame('acme-bv', $tenants[0]['subdomain']);
    }

    public function testUpdatingATenantToAnAlreadyUsedSubdomainFails(): void
    {
        $this->login('admin@kozijnr.nl', 'super-secret-123');

        $this->createTenant('Acme', 'acme');
        $this->createTenant('Beta', 'beta');

        $this->client->request('PATCH', '/api/admin/tenants/acme', server: [
            'HTTP_HOST' => self::ADMIN_HOST,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['name' => 'Acme', 'slug' => 'beta']));

        self::assertResponseStatusCodeSame(422);
    }

    public function testUpdatingAnUnknownTenantReturnsNotFound(): void
    {
        $this->login('admin@kozijnr.nl', 'super-secret-123');

        $this->client->request('PATCH', '/api/admin/tenants/does-not-exist', server: [
            'HTTP_HOST' => self::ADMIN_HOST,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['name' => 'Acme', 'slug' => 'acme']));

        self::assertResponseStatusCodeSame(404);
    }

    public function testArchivingATenantHidesItFromTheDefaultListingButShowsItInTheArchivedListing(): void
    {
        $this->login('admin@kozijnr.nl', 'super-secret-123');
        $this->createTenant('Acme', 'acme');

        $this->client->request('POST', '/api/admin/tenants/acme/archive', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        self::assertResponseIsSuccessful();
        $archived = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertTrue($archived['archived']);

        $this->client->request('GET', '/api/admin/tenants', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        self::assertSame([], json_decode((string) $this->client->getResponse()->getContent(), true));

        $this->client->request('GET', '/api/admin/tenants?archived=true', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        $archivedList = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(1, $archivedList);
        self::assertSame('acme', $archivedList[0]['subdomain']);
    }

    public function testUnarchivingATenantMovesItBackToTheDefaultListing(): void
    {
        $this->login('admin@kozijnr.nl', 'super-secret-123');
        $this->createTenant('Acme', 'acme');
        $this->client->request('POST', '/api/admin/tenants/acme/archive', server: ['HTTP_HOST' => self::ADMIN_HOST]);

        $this->client->request('POST', '/api/admin/tenants/acme/unarchive', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        self::assertResponseIsSuccessful();
        $unarchived = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertFalse($unarchived['archived']);

        $this->client->request('GET', '/api/admin/tenants', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        $tenants = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(1, $tenants);
        self::assertSame('acme', $tenants[0]['subdomain']);
    }

    public function testArchivingAnArchivedTenantMakesItUnreachableForLogin(): void
    {
        $this->login('admin@kozijnr.nl', 'super-secret-123');
        $this->createTenant('Acme', 'acme');
        $this->client->request('POST', '/api/admin/tenants/acme/archive', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        self::assertResponseIsSuccessful();

        $this->client->request('POST', '/api/login', server: [
            'HTTP_HOST' => 'acme.' . self::BASE_DOMAIN,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'anyone@acme.test', 'password' => 'whatever']));

        self::assertResponseStatusCodeSame(404);
    }

    public function testArchivingAnUnknownTenantReturnsNotFound(): void
    {
        $this->login('admin@kozijnr.nl', 'super-secret-123');

        $this->client->request('POST', '/api/admin/tenants/does-not-exist/archive', server: ['HTTP_HOST' => self::ADMIN_HOST]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testASuperAdminSessionDoesNotWorkOnATenantSubdomain(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('CREATE SCHEMA IF NOT EXISTS tenant_acme');
        $connection->executeStatement(
            "INSERT INTO public.tenants (name, subdomain, schema_name, created_at) "
            . "VALUES ('Acme', 'acme', 'tenant_acme', NOW())"
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

    /** @return array{name: string, subdomain: string, createdAt: string, archived: bool, archivedAt: ?string, tenantAdmin: array{email: string, password: string}} */
    private function createTenant(string $name, string $slug, ?string $adminEmail = null): array
    {
        $this->client->request('POST', '/api/admin/tenants', server: [
            'HTTP_HOST' => self::ADMIN_HOST,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['name' => $name, 'slug' => $slug, 'adminEmail' => $adminEmail ?? "admin@{$slug}.test"]));

        self::assertResponseStatusCodeSame(201);

        return json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    private function login(string $email, string $password): void
    {
        $this->client->request('POST', '/api/admin/login', server: [
            'HTTP_HOST' => self::ADMIN_HOST,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => $email, 'password' => $password]));
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
