<?php

namespace App\Tests\Tenancy\Infrastructure\Controller;

use App\Tenancy\Application\ProvisionTenant;
use App\TenantUser\Application\CreateTenantUser;
use App\TenantUser\Domain\TenantUser;
use App\TenantUser\Infrastructure\Security\TenantApiTokenCookie;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * End-to-end coverage of the KOZ-34 DoD: a tenant-admin can view/update
 * their tenant's default locale and upload/preview a login image via
 * /api/settings*, the login image is publicly fetchable from
 * /api/login-image with no session at all, both settings live on the
 * public-schema `Tenant` row (never leaking into another tenant's schema),
 * and none of it is reachable by a non-admin tenant user or from another
 * tenant's subdomain.
 */
final class TenantSettingsApiTest extends WebTestCase
{
    private const BASE_DOMAIN = 'localhost';

    private KernelBrowser $client;
    private string $storageRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->storageRoot = self::getContainer()->getParameter('app.storage.local_root');
        $this->resetDatabase();
    }

    protected function tearDown(): void
    {
        $this->resetDatabase();
        $this->removeDirectory($this->storageRoot);

        parent::tearDown();
    }

    public function testATenantAdminCanReadTheDefaultSettings(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'beheerder@acme.test', 'correct-password', [TenantUser::ROLE_TENANT_ADMIN]);
        $this->login('acme', 'beheerder@acme.test', 'correct-password');

        $this->client->request('GET', '/api/settings', server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN]);

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('nl', $body['defaultLocale']);
        self::assertFalse($body['hasLoginImage']);
    }

    public function testATenantAdminCanUpdateTheDefaultLocale(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'beheerder@acme.test', 'correct-password', [TenantUser::ROLE_TENANT_ADMIN]);
        $this->login('acme', 'beheerder@acme.test', 'correct-password');

        $this->client->request('PATCH', '/api/settings/locale', server: [
            'HTTP_HOST' => 'acme.' . self::BASE_DOMAIN,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['defaultLocale' => 'en']));

        self::assertResponseIsSuccessful();
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('en', $body['defaultLocale']);

        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $stored = $connection->fetchOne('SELECT default_locale FROM tenants WHERE subdomain = :s', ['s' => 'acme']);
        self::assertSame('en', $stored);
    }

    public function testUpdatingToAnUnsupportedLocaleFailsWithAStableErrorKey(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'beheerder@acme.test', 'correct-password', [TenantUser::ROLE_TENANT_ADMIN]);
        $this->login('acme', 'beheerder@acme.test', 'correct-password');

        $this->client->request('PATCH', '/api/settings/locale', server: [
            'HTTP_HOST' => 'acme.' . self::BASE_DOMAIN,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['defaultLocale' => 'fr']));

        self::assertResponseStatusCodeSame(422);
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('tenants.error.invalidLocale', $body['errorKey']);
    }

    public function testAPlainTenantUserCannotReadOrUpdateSettings(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'gebruiker@acme.test', 'correct-password', [TenantUser::DEFAULT_ROLE]);
        $this->login('acme', 'gebruiker@acme.test', 'correct-password');

        $this->client->request('GET', '/api/settings', server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN]);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('PATCH', '/api/settings/locale', server: [
            'HTTP_HOST' => 'acme.' . self::BASE_DOMAIN,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['defaultLocale' => 'en']));
        self::assertResponseStatusCodeSame(403);
    }

    public function testAnUnauthenticatedRequestCannotReadOrUpdateSettings(): void
    {
        $this->provisionTenant('acme');

        $this->client->request('GET', '/api/settings', server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testUploadingALoginImageThenFetchingItPubliclyRoundTrips(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'beheerder@acme.test', 'correct-password', [TenantUser::ROLE_TENANT_ADMIN]);
        $this->login('acme', 'beheerder@acme.test', 'correct-password');

        $contents = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        );
        $uploadedFile = $this->makeUploadedFile($contents, 'login-bg.png', 'image/png');

        $this->client->request('POST', '/api/settings/login-image', server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN], files: ['image' => $uploadedFile]);
        self::assertResponseStatusCodeSame(201);
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertTrue($body['hasLoginImage']);

        // Same client but with its cookie jar cleared: proves the login
        // image is fetchable purely from the subdomain, with no session at
        // all (KOZ-34 kernpunt).
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/api/login-image', server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN]);

        self::assertResponseIsSuccessful();
        self::assertSame('image/png', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertSame($contents, $this->client->getResponse()->getContent());

        // KOZ-34 rework: without this header, Chrome's ORB (Opaque Response
        // Blocking) blocks the response when loaded via a plain
        // cross-origin `<img src>` from the tenant subdomain — the browser
        // sends no Origin header for that kind of request, so
        // CorsListener's Access-Control-* headers never apply here at all;
        // this is a separate, unconditional opt-out of ORB specifically.
        self::assertSame('cross-origin', $this->client->getResponse()->headers->get('Cross-Origin-Resource-Policy'));
    }

    public function testFetchingTheLoginImageWhenNoneWasUploadedReturns404(): void
    {
        $this->provisionTenant('acme');

        $this->client->request('GET', '/api/login-image', server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testUploadingAnUnsupportedFileTypeIsRejectedWithAnErrorKey(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'beheerder@acme.test', 'correct-password', [TenantUser::ROLE_TENANT_ADMIN]);
        $this->login('acme', 'beheerder@acme.test', 'correct-password');

        $uploadedFile = $this->makeUploadedFile('<?php echo "hi"; ?>', 'shell.php', 'application/x-php');

        $this->client->request('POST', '/api/settings/login-image', server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN], files: ['image' => $uploadedFile]);

        self::assertResponseStatusCodeSame(422);
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('tenantSettings.error.unsupportedMimeType', $body['errorKey']);
    }

    /**
     * Core tenant-isolation claim (mirrors TenantOwnUsersApiTest /
     * TenantLoginApiTest): tenant B's login image and default locale are
     * completely unaffected by anything done on tenant A's subdomain, and
     * a token issued on tenant A grants no access on tenant B's settings.
     */
    public function testSettingsAreFullyIsolatedBetweenTenants(): void
    {
        $this->provisionTenant('acme');
        $this->provisionTenant('beta');
        $this->createTenantUser('acme', 'beheerder@acme.test', 'correct-password', [TenantUser::ROLE_TENANT_ADMIN]);

        $this->login('acme', 'beheerder@acme.test', 'correct-password');
        $this->client->request('PATCH', '/api/settings/locale', server: [
            'HTTP_HOST' => 'acme.' . self::BASE_DOMAIN,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['defaultLocale' => 'en']));
        self::assertResponseIsSuccessful();
        $acmeToken = $this->responseCookie()?->getValue();

        // Tenant beta's own settings are still untouched. Cookie jar
        // cleared first: the tenant-api-token cookie is domain-scoped
        // above the subdomain (see TenantApiTokenCookie::issue), so it
        // would otherwise ride along to beta's subdomain too and get
        // rejected as an (unrelated) invalid token before ever reaching
        // this PUBLIC_ACCESS route.
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/api/login-image', server: ['HTTP_HOST' => 'beta.' . self::BASE_DOMAIN]);
        self::assertResponseStatusCodeSame(404);

        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $betaLocale = $connection->fetchOne('SELECT default_locale FROM tenants WHERE subdomain = :s', ['s' => 'beta']);
        self::assertSame('nl', $betaLocale);

        // A token issued on acme's subdomain grants no access replayed
        // against beta's.
        $this->client->request('GET', '/api/settings', server: [
            'HTTP_HOST' => 'beta.' . self::BASE_DOMAIN,
            'HTTP_COOKIE' => TenantApiTokenCookie::NAME . '=' . $acmeToken,
        ]);
        self::assertResponseStatusCodeSame(401);
    }

    private function login(string $subdomain, string $email, string $password): void
    {
        $this->client->request('POST', '/api/login', server: [
            'HTTP_HOST' => $subdomain . '.' . self::BASE_DOMAIN,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => $email, 'password' => $password]));
        self::assertResponseIsSuccessful();
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

    private function makeUploadedFile(string $contents, string $originalName, string $mimeType): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'koz34-upload-');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $originalName, $mimeType, null, true);
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

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }

        rmdir($path);
    }
}
