<?php

namespace App\Tests\User\Infrastructure\Controller;

use App\User\Application\CreateSuperAdmin;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end coverage of the KOZ-30 admin-user-management flow: a logged-in
 * super admin can list existing admin users and create a new one from the
 * admin UI, and neither works without authenticating as a super admin
 * first. Modeled directly on TenantAdminApiTest.
 */
final class AdminUserApiTest extends WebTestCase
{
    private const ADMIN_HOST = 'admin.localhost';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $connection->executeStatement('DELETE FROM public.users');

        self::getContainer()->get(CreateSuperAdmin::class)('admin@kozijnr.nl', 'super-secret-123');
    }

    protected function tearDown(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $connection->executeStatement('DELETE FROM public.users');

        parent::tearDown();
    }

    public function testUnauthenticatedRequestsToTheAdminUserApiAreRejected(): void
    {
        $this->client->request('GET', '/api/admin/users', server: ['HTTP_HOST' => self::ADMIN_HOST]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testLoggedInSuperAdminCanListAndCreateAdminUsers(): void
    {
        $this->login('admin@kozijnr.nl', 'super-secret-123');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/admin/users', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        self::assertResponseIsSuccessful();
        $initial = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(1, $initial);
        self::assertSame('admin@kozijnr.nl', $initial[0]['email']);

        $created = $this->createAdminUser('new-admin@kozijnr.nl');

        self::assertSame('new-admin@kozijnr.nl', $created['email']);
        self::assertContains('ROLE_SUPER_ADMIN', $created['roles']);
        self::assertNotEmpty($created['password']);

        $this->client->request('GET', '/api/admin/users', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        $users = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(2, $users);
        $emails = array_column($users, 'email');
        self::assertContains('new-admin@kozijnr.nl', $emails);
    }

    public function testTheGeneratedPasswordActuallyLogsIn(): void
    {
        $this->login('admin@kozijnr.nl', 'super-secret-123');
        $created = $this->createAdminUser('login-check@kozijnr.nl');

        // Reuses the same client rather than a second static::createClient()
        // (booting a second kernel isn't supported), but that's fine here —
        // logging in as a different user simply replaces the session.
        $this->login('login-check@kozijnr.nl', $created['password']);

        self::assertResponseIsSuccessful();
    }

    public function testCreatingAnAdminUserWithAnInvalidEmailFails(): void
    {
        $this->login('admin@kozijnr.nl', 'super-secret-123');

        $this->client->request('POST', '/api/admin/users', server: [
            'HTTP_HOST' => self::ADMIN_HOST,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'not-an-email']));

        self::assertResponseStatusCodeSame(422);

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('users.error.emailInvalid', $payload['errorKey']);
    }

    public function testCreatingAnAdminUserWithAnEmptyEmailFails(): void
    {
        $this->login('admin@kozijnr.nl', 'super-secret-123');

        $this->client->request('POST', '/api/admin/users', server: [
            'HTTP_HOST' => self::ADMIN_HOST,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => '']));

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreatingAnAdminUserWithADuplicateEmailFailsWithAStableErrorKeyInsteadOfA500(): void
    {
        $this->login('admin@kozijnr.nl', 'super-secret-123');

        $this->client->request('POST', '/api/admin/users', server: [
            'HTTP_HOST' => self::ADMIN_HOST,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'admin@kozijnr.nl']));

        self::assertResponseStatusCodeSame(422);

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('users.error.emailAlreadyExists', $payload['errorKey']);
        self::assertSame(['email' => 'admin@kozijnr.nl'], $payload['errorKeyParams']);

        // Still only the one super admin from setUp() — the failed attempt
        // created nothing.
        $this->client->request('GET', '/api/admin/users', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        $users = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(1, $users);
    }

    /** @return array{email: string, roles: list<string>, password: string} */
    private function createAdminUser(string $email): array
    {
        $this->client->request('POST', '/api/admin/users', server: [
            'HTTP_HOST' => self::ADMIN_HOST,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => $email]));

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
