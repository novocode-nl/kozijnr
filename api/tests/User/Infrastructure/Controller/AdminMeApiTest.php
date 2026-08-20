<?php

namespace App\Tests\User\Infrastructure\Controller;

use App\User\Application\CreateSuperAdmin;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * KOZ-14 rework: GET /api/admin/me is the whoami endpoint the frontend
 * proxy (web/proxy.ts) needs to turn "there is a PHPSESSID cookie" into
 * "there is a *valid* super-admin session" before letting a request
 * through to any admin.<domein> route — mirroring exactly what
 * TenantUser's GET /api/me (MeController) already does for the tenant
 * cookie, just against the session-based `super_admin` firewall instead
 * of the stateless `tenant_users` one.
 *
 * No new access_control entry was needed: `/api/admin/me` already falls
 * under the existing `{ path: ^/api/admin, roles: IS_AUTHENTICATED_FULLY }`
 * rule (config/packages/security.yaml) — only `/api/admin/(login|logout)`
 * is carved out as public.
 */
final class AdminMeApiTest extends WebTestCase
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

    public function testRequestsWithoutASessionAreRejected(): void
    {
        $this->client->request('GET', '/api/admin/me', server: ['HTTP_HOST' => self::ADMIN_HOST]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testALoggedInSuperAdminCanAccessTheProtectedMeEndpoint(): void
    {
        $this->client->request('POST', '/api/admin/login', server: [
            'HTTP_HOST' => self::ADMIN_HOST,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'admin@kozijnr.nl', 'password' => 'super-secret-123']));
        self::assertResponseIsSuccessful();

        // No Authorization header — the client's cookie jar already carries
        // the session cookie the login response set, exactly like a real
        // browser would for the `super_admin` firewall's session.
        $this->client->request('GET', '/api/admin/me', server: ['HTTP_HOST' => self::ADMIN_HOST]);

        // KOZ-14 rework (round 4, non-blocking review finding): the only
        // caller (web/proxy.ts's hasValidAdminSession) only ever inspects
        // the status code, never the body — returning the admin's email
        // and roles here was unnecessary data exposure. A bare 200 is
        // enough to say "yes, valid session".
        self::assertResponseIsSuccessful();
        self::assertSame('', $this->client->getResponse()->getContent());
    }

    public function testAnInvalidSessionCookieIsRejectedJustLikeAMissingOne(): void
    {
        // A cookie that merely has the right name but doesn't correspond to
        // any session the backend recognises (expired, forged, or from a
        // session store that was cleared) must be treated identically to
        // "no cookie at all" — not distinguished in any way that could leak
        // information about session validity.
        $this->client->getCookieJar()->set(new \Symfony\Component\BrowserKit\Cookie('PHPSESSID', 'not-a-real-session-id'));

        $this->client->request('GET', '/api/admin/me', server: ['HTTP_HOST' => self::ADMIN_HOST]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testTheMeEndpointIsNotReachableOnTheBareMainDomain(): void
    {
        // Same treatment AdminRouteGuardListenerTest already proves for
        // /api/admin/login and /api/admin/tenants — the bare main domain
        // is neither a tenant nor the admin domain, so it gets a 404, not
        // a 401 that would leak that the route exists elsewhere.
        $this->client->request('GET', '/api/admin/me', server: ['HTTP_HOST' => 'localhost']);
        self::assertResponseStatusCodeSame(404);
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
