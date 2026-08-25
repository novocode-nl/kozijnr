<?php

namespace App\Tests\TenantUser\Infrastructure\Controller;

use App\Tenancy\Application\ProvisionTenant;
use App\TenantUser\Application\CreateTenantUser;
use App\TenantUser\Domain\TenantUser;
use App\TenantUser\Infrastructure\Security\TenantApiTokenCookie;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end coverage of the tenant-own self-service "Gebruiker toevoegen"
 * flow (KOZ-31 rework): a logged-in tenant-admin can create additional
 * tenant users for their *own* tenant directly from
 * {tenant}.<domein>/users (POST /api/users), without ever going through
 * admin.<domein> — the counterpart of TenantAdminApiTest's admin-side
 * coverage.
 *
 * The central claim under test, mirroring TenantAdminPermissionAuthorizationTest
 * and TenantLoginApiTest's tenant-isolation tests: a tenant-admin of one
 * tenant can never create a user in another tenant (there is no
 * client-supplied subdomain to manipulate at all — the tenant is decided
 * exclusively by which subdomain the request authenticated on), and a
 * plain ROLE_TENANT_USER (no admin role) cannot create users at all.
 */
final class TenantOwnUsersApiTest extends WebTestCase
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

    public function testALoggedInTenantAdminCanCreateAnAdditionalUserForTheirOwnTenant(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'beheerder@acme.test', 'correct-password', [TenantUser::ROLE_TENANT_ADMIN]);
        $this->login('acme', 'beheerder@acme.test', 'correct-password');

        $this->client->request('POST', '/api/users', server: [
            'HTTP_HOST' => 'acme.' . self::BASE_DOMAIN,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'collega@acme.test', 'role' => TenantUser::DEFAULT_ROLE]));

        self::assertResponseStatusCodeSame(201);
        $created = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('collega@acme.test', $created['email']);
        self::assertSame([TenantUser::DEFAULT_ROLE], $created['roles']);
        self::assertNotEmpty($created['password']);

        $this->client->request('GET', '/api/users', server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN]);
        self::assertResponseIsSuccessful();
        $users = json_decode((string) $this->client->getResponse()->getContent(), true);
        $emails = array_column($users, 'email');
        self::assertContains('collega@acme.test', $emails);
        self::assertContains('beheerder@acme.test', $emails);
    }

    public function testALoggedInTenantAdminCanCreateAnAdditionalTenantAdmin(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'beheerder@acme.test', 'correct-password', [TenantUser::ROLE_TENANT_ADMIN]);
        $this->login('acme', 'beheerder@acme.test', 'correct-password');

        $this->client->request('POST', '/api/users', server: [
            'HTTP_HOST' => 'acme.' . self::BASE_DOMAIN,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'tweede-beheerder@acme.test', 'role' => TenantUser::ROLE_TENANT_ADMIN]));

        self::assertResponseStatusCodeSame(201);
        $created = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame([TenantUser::ROLE_TENANT_ADMIN], $created['roles']);
    }

    public function testAPlainTenantUserWithoutTheAdminRoleCannotCreateUsers(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'gebruiker@acme.test', 'correct-password', [TenantUser::DEFAULT_ROLE]);
        $this->login('acme', 'gebruiker@acme.test', 'correct-password');

        $this->client->request('POST', '/api/users', server: [
            'HTTP_HOST' => 'acme.' . self::BASE_DOMAIN,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'collega@acme.test', 'role' => TenantUser::DEFAULT_ROLE]));

        self::assertResponseStatusCodeSame(403);
    }

    public function testAnUnauthenticatedRequestCannotCreateUsers(): void
    {
        $this->provisionTenant('acme');

        $this->client->request('POST', '/api/users', server: [
            'HTTP_HOST' => 'acme.' . self::BASE_DOMAIN,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'collega@acme.test', 'role' => TenantUser::DEFAULT_ROLE]));

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * The core tenant-isolation claim: there is no subdomain in the
     * request body at all for this route, so a tenant-admin authenticated
     * on tenant A's subdomain has no way whatsoever to create a user in
     * tenant B — the write always lands wherever the request itself
     * authenticated, never anywhere a client could name.
     */
    public function testATenantAdminCanNeverCreateAUserInAnotherTenant(): void
    {
        $this->provisionTenant('acme');
        $this->provisionTenant('beta');
        $this->createTenantUser('acme', 'beheerder@acme.test', 'correct-password', [TenantUser::ROLE_TENANT_ADMIN]);
        $this->login('acme', 'beheerder@acme.test', 'correct-password');
        $token = $this->responseCookie()?->getValue();
        self::assertNotNull($token);

        // Same stolen/replayed-cookie shape as
        // TenantLoginApiTest::testATokenIssuedOnOneTenantSubdomainGrantsNoAccessOnAnotherTenantSubdomain:
        // even if an attacker replayed tenant A's token against tenant B's
        // subdomain, the token lookup itself runs inside tenant B's schema
        // and simply doesn't find it there.
        $this->client->request('POST', '/api/users', server: [
            'HTTP_HOST' => 'beta.' . self::BASE_DOMAIN,
            'HTTP_COOKIE' => TenantApiTokenCookie::NAME . '=' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'indringer@beta.test', 'role' => TenantUser::DEFAULT_ROLE]));

        self::assertResponseStatusCodeSame(401);

        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO tenant_beta, public');
        $count = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM tenant_users WHERE email = :email',
            ['email' => 'indringer@beta.test'],
        );
        $connection->executeStatement('SET search_path TO public');

        self::assertSame(0, $count);
    }

    public function testCreatingAUserWithADuplicateEmailFailsWithAStableErrorKey(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'beheerder@acme.test', 'correct-password', [TenantUser::ROLE_TENANT_ADMIN]);
        $this->login('acme', 'beheerder@acme.test', 'correct-password');

        $this->client->request('POST', '/api/users', server: [
            'HTTP_HOST' => 'acme.' . self::BASE_DOMAIN,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'beheerder@acme.test', 'role' => TenantUser::DEFAULT_ROLE]));

        self::assertResponseStatusCodeSame(422);
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('tenants.error.adminEmailAlreadyExists', $payload['errorKey']);
    }

    public function testCreatingAUserWithAnInvalidRoleFails(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'beheerder@acme.test', 'correct-password', [TenantUser::ROLE_TENANT_ADMIN]);
        $this->login('acme', 'beheerder@acme.test', 'correct-password');

        $this->client->request('POST', '/api/users', server: [
            'HTTP_HOST' => 'acme.' . self::BASE_DOMAIN,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'iemand@acme.test', 'role' => 'ROLE_SUPER_ADMIN']));

        self::assertResponseStatusCodeSame(422);
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('tenants.error.userRoleInvalid', $payload['errorKey']);
    }

    public function testTheEndpointIsNotReachableOnTheBareMainDomain(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'beheerder@acme.test', 'correct-password', [TenantUser::ROLE_TENANT_ADMIN]);
        $this->login('acme', 'beheerder@acme.test', 'correct-password');
        $token = $this->responseCookie()?->getValue();

        $this->client->request('POST', '/api/users', server: [
            'HTTP_HOST' => self::BASE_DOMAIN,
            'HTTP_COOKIE' => TenantApiTokenCookie::NAME . '=' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'iemand@example.test', 'role' => TenantUser::DEFAULT_ROLE]));

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheEndpointIsNotReachableOnTheAdminSubdomain(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'beheerder@acme.test', 'correct-password', [TenantUser::ROLE_TENANT_ADMIN]);
        $this->login('acme', 'beheerder@acme.test', 'correct-password');
        $token = $this->responseCookie()?->getValue();

        $this->client->request('POST', '/api/users', server: [
            'HTTP_HOST' => 'admin.' . self::BASE_DOMAIN,
            'HTTP_COOKIE' => TenantApiTokenCookie::NAME . '=' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'iemand@example.test', 'role' => TenantUser::DEFAULT_ROLE]));

        self::assertResponseStatusCodeSame(404);
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

    /** @param list<string> $roles */
    private function createTenantUser(string $subdomain, string $email, string $password, array $roles = []): void
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

        static::getContainer()->get(CreateTenantUser::class)($email, $password, $roles);

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

    private function responseCookie(): ?\Symfony\Component\HttpFoundation\Cookie
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->getName() === TenantApiTokenCookie::NAME) {
                return $cookie;
            }
        }

        return null;
    }
}
