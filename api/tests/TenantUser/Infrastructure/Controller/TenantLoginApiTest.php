<?php

namespace App\Tests\TenantUser\Infrastructure\Controller;

use App\Tenancy\Application\ProvisionTenant;
use App\TenantUser\Application\CreateTenantUser;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end coverage of the KOZ-11 tenant-user login flow: a tenant user
 * can log in with email+password on their own tenant subdomain and get a
 * bearer token back, invalid combinations are rejected with one generic
 * message, and — the DoD's central claim — a token issued on tenant A's
 * subdomain grants no access at all via tenant B's subdomain.
 *
 * Uses the real tenant provisioning machinery (ProvisionTenant, KOZ-7) to
 * create real tenant schemas, exactly like TenantAdminApiTest does for the
 * super-admin flow.
 */
final class TenantLoginApiTest extends WebTestCase
{
    private const BASE_DOMAIN = 'localhost';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->resetDatabase();
    }

    protected function tearDown(): void
    {
        $this->resetDatabase();

        parent::tearDown();
    }

    public function testLoggingInWithValidCredentialsReturnsAToken(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'user@acme.test', 'correct-password');

        $this->login('acme', 'user@acme.test', 'correct-password');

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('token', $payload);
        self::assertIsString($payload['token']);
        self::assertNotSame('', $payload['token']);
    }

    public function testLoggingInWithAnUnknownEmailFailsWithAGenericMessage(): void
    {
        $this->provisionTenant('acme');

        $this->login('acme', 'unknown@acme.test', 'whatever');

        self::assertResponseStatusCodeSame(401);
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(['message' => 'Invalid credentials.'], $payload);
    }

    public function testLoggingInWithAWrongPasswordFailsWithTheExactSameGenericMessage(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'user@acme.test', 'correct-password');

        $this->login('acme', 'user@acme.test', 'wrong-password');

        self::assertResponseStatusCodeSame(401);
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(['message' => 'Invalid credentials.'], $payload);
    }

    public function testThePasswordIsStoredHashedNeverInPlaintext(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'user@acme.test', 'correct-password');

        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO tenant_acme, public');
        $storedPassword = (string) $connection->fetchOne(
            'SELECT password FROM tenant_users WHERE email = :email',
            ['email' => 'user@acme.test'],
        );
        $connection->executeStatement('SET search_path TO public');

        self::assertNotSame('correct-password', $storedPassword);
        self::assertGreaterThan(20, strlen($storedPassword));
    }

    public function testLoginIsNotReachableOnTheBareMainDomain(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'user@acme.test', 'correct-password');

        $this->client->request('POST', '/api/login', server: [
            'HTTP_HOST' => self::BASE_DOMAIN,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'user@acme.test', 'password' => 'correct-password']));

        self::assertResponseStatusCodeSame(404);
    }

    public function testAnAuthenticatedTenantUserCanAccessTheProtectedMeEndpoint(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'user@acme.test', 'correct-password');
        $this->login('acme', 'user@acme.test', 'correct-password');
        $token = json_decode((string) $this->client->getResponse()->getContent(), true)['token'];

        $this->client->request('GET', '/api/me', server: [
            'HTTP_HOST' => 'acme.' . self::BASE_DOMAIN,
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('user@acme.test', $payload['email']);
        self::assertSame(['ROLE_TENANT_USER'], $payload['roles']);
    }

    public function testTheProtectedMeEndpointRejectsRequestsWithoutAToken(): void
    {
        $this->provisionTenant('acme');

        $this->client->request('GET', '/api/me', server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testTheProtectedMeEndpointRejectsAnInvalidToken(): void
    {
        $this->provisionTenant('acme');

        $this->client->request('GET', '/api/me', server: [
            'HTTP_HOST' => 'acme.' . self::BASE_DOMAIN,
            'HTTP_AUTHORIZATION' => 'Bearer this-token-does-not-exist',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * The KOZ-11 DoD's central claim: a token obtained on tenant A's
     * subdomain grants no access via tenant B's subdomain — proven here the
     * same way TenantAdminApiTest proves it for the super-admin session
     * (testASuperAdminSessionDoesNotWorkOnATenantSubdomain), by replaying
     * the exact same credential against the other tenant's subdomain.
     *
     * This is a structural guarantee, not a runtime "does this token belong
     * to this tenant" check: the token row issued on tenant A's schema
     * simply does not exist when queried while search_path points at
     * tenant B's schema (see App\TenantUser\Infrastructure\Security\TenantApiTokenHandler).
     */
    public function testATokenIssuedOnOneTenantSubdomainGrantsNoAccessOnAnotherTenantSubdomain(): void
    {
        $this->provisionTenant('acme');
        $this->provisionTenant('beta');
        $this->createTenantUser('acme', 'user@acme.test', 'correct-password');

        $this->login('acme', 'user@acme.test', 'correct-password');
        self::assertResponseIsSuccessful();
        $token = json_decode((string) $this->client->getResponse()->getContent(), true)['token'];

        // Same client/token, replayed against tenant beta's subdomain.
        $this->client->request('GET', '/api/me', server: [
            'HTTP_HOST' => 'beta.' . self::BASE_DOMAIN,
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseStatusCodeSame(401);

        // Prove the token still legitimately works on its own tenant, so
        // the 401 above is really about tenant-binding and not e.g. the
        // token having been invalidated as a side effect of the request.
        $this->client->request('GET', '/api/me', server: [
            'HTTP_HOST' => 'acme.' . self::BASE_DOMAIN,
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseIsSuccessful();
    }

    /**
     * KOZ-15: logging out revokes the current bearer token end to end, via
     * the real /api/logout route + firewall + repository. Sliding expiry
     * and the generic expired/revoked-vs-unknown error handling are
     * exercised at the unit level (TenantApiTokenTest,
     * TenantApiTokenHandlerTest) instead — an end-to-end test would need to
     * fast-forward real time, which those lower-level tests already cover
     * without that complexity.
     */
    public function testALogoutRevokesTheCurrentTokenSoItNoLongerWorks(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'user@acme.test', 'correct-password');
        $this->login('acme', 'user@acme.test', 'correct-password');
        $token = json_decode((string) $this->client->getResponse()->getContent(), true)['token'];

        $this->client->request('POST', '/api/logout', server: [
            'HTTP_HOST' => 'acme.' . self::BASE_DOMAIN,
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/me', server: [
            'HTTP_HOST' => 'acme.' . self::BASE_DOMAIN,
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testLogoutIsNotReachableOnTheBareMainDomain(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'user@acme.test', 'correct-password');
        $this->login('acme', 'user@acme.test', 'correct-password');
        $token = json_decode((string) $this->client->getResponse()->getContent(), true)['token'];

        $this->client->request('POST', '/api/logout', server: [
            'HTTP_HOST' => self::BASE_DOMAIN,
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testLogoutWithoutATokenIsRejected(): void
    {
        $this->provisionTenant('acme');

        $this->client->request('POST', '/api/logout', server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN]);

        self::assertResponseStatusCodeSame(401);
    }

    private function login(string $subdomain, string $email, string $password): void
    {
        $this->client->request('POST', '/api/login', server: [
            'HTTP_HOST' => $subdomain . '.' . self::BASE_DOMAIN,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => $email, 'password' => $password]));
    }

    private function provisionTenant(string $name): void
    {
        static::getContainer()->get(ProvisionTenant::class)($name);
    }

    private function createTenantUser(string $subdomain, string $email, string $password): void
    {
        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $schemaName = (string) $connection->fetchOne(
            'SELECT schema_name FROM tenants WHERE subdomain = :subdomain',
            ['subdomain' => $subdomain],
        );
        $connection->executeStatement(sprintf(
            'SET search_path TO %s, public',
            $connection->quoteSingleIdentifier($schemaName),
        ));

        static::getContainer()->get(CreateTenantUser::class)($email, $password, []);

        $connection->executeStatement('SET search_path TO public');
    }

    private function resetDatabase(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $connection->executeStatement('DROP SCHEMA IF EXISTS tenant_acme CASCADE');
        $connection->executeStatement('DROP SCHEMA IF EXISTS tenant_beta CASCADE');
        $connection->executeStatement('DELETE FROM public.tenants');
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
