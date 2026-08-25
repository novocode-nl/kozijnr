<?php

namespace App\Tests\ProfilePhoto\Infrastructure\Controller;

use App\Tenancy\Application\ProvisionTenant;
use App\TenantUser\Application\CreateTenantUser;
use App\TenantUser\Domain\TenantUser;
use App\TenantUser\Infrastructure\Security\TenantApiTokenCookie;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * KOZ-33: end-to-end coverage of the tenant-user profile photo flow — the
 * tenant-realm counterpart of ProfilePhotoApiTest, reusing the same
 * App\ProfilePhoto\Application\UploadProfilePhoto / GetProfilePhoto command
 * and query handlers with a TenantUser's own id as the owner instead of a
 * super-admin User's. The central additional claim under test (mirroring
 * TenantOwnUsersApiTest's tenant-isolation coverage): a tenant user can
 * never fetch or affect another tenant user's (or another tenant's)
 * profile photo.
 */
final class TenantProfilePhotoApiTest extends WebTestCase
{
    private const BASE_DOMAIN = 'localhost';
    private const PHOTO_URL = '/api/me/profile-photo';

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

    public function testUploadingWithoutBeingLoggedInIsRejected(): void
    {
        $this->provisionTenant('acme');

        $this->client->request('POST', self::PHOTO_URL, server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testFetchingWithoutBeingLoggedInIsRejected(): void
    {
        $this->provisionTenant('acme');

        $this->client->request('GET', self::PHOTO_URL, server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testFetchingWhenNoPhotoWasUploadedReturns404(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'gebruiker@acme.test', 'correct-password');
        $this->login('acme', 'gebruiker@acme.test', 'correct-password');

        $this->client->request('GET', self::PHOTO_URL, server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testUploadingAPhotoThenFetchingItRoundTrips(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'gebruiker@acme.test', 'correct-password');
        $this->login('acme', 'gebruiker@acme.test', 'correct-password');

        $contents = $this->onePixelPng();
        $uploadedFile = $this->makeUploadedFile($contents, 'avatar.png', 'image/png');

        $this->client->request('POST', self::PHOTO_URL, server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN], files: ['photo' => $uploadedFile]);

        self::assertResponseStatusCodeSame(201);
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('image/png', $body['mimeType']);
        self::assertSame('avatar.png', $body['originalFilename']);
        self::assertSame(strlen($contents), $body['sizeInBytes']);
        self::assertArrayNotHasKey('storageKey', $body);

        $this->client->request('GET', self::PHOTO_URL, server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN]);

        self::assertResponseIsSuccessful();
        self::assertSame('image/png', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertSame($contents, $this->client->getResponse()->getContent());
    }

    public function testUploadingAnUnsupportedFileTypeIsRejectedWithAnErrorKey(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'gebruiker@acme.test', 'correct-password');
        $this->login('acme', 'gebruiker@acme.test', 'correct-password');

        $uploadedFile = $this->makeUploadedFile('<?php echo "hi"; ?>', 'shell.php', 'application/x-php');

        $this->client->request('POST', self::PHOTO_URL, server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN], files: ['photo' => $uploadedFile]);

        self::assertResponseStatusCodeSame(422);
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('profilePhoto.error.unsupportedMimeType', $body['errorKey']);
    }

    public function testUploadingWithoutAFileIsRejected(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'gebruiker@acme.test', 'correct-password');
        $this->login('acme', 'gebruiker@acme.test', 'correct-password');

        $this->client->request('POST', self::PHOTO_URL, server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN]);

        self::assertResponseStatusCodeSame(422);
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('profilePhoto.error.missingFile', $body['errorKey']);
    }

    public function testUploadingASecondPhotoReplacesTheFirst(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'gebruiker@acme.test', 'correct-password');
        $this->login('acme', 'gebruiker@acme.test', 'correct-password');

        $pngContents = $this->onePixelPng();
        $jpegContents = base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AVN//2Q==',
        );

        $first = $this->makeUploadedFile($pngContents, 'first.png', 'image/png');
        $this->client->request('POST', self::PHOTO_URL, server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN], files: ['photo' => $first]);
        self::assertResponseStatusCodeSame(201);

        $second = $this->makeUploadedFile($jpegContents, 'second.jpg', 'image/jpeg');
        $this->client->request('POST', self::PHOTO_URL, server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN], files: ['photo' => $second]);
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', self::PHOTO_URL, server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN]);
        self::assertSame($jpegContents, $this->client->getResponse()->getContent());

        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO tenant_acme, public');
        $count = (int) $connection->fetchOne('SELECT COUNT(*) FROM profile_photos');
        $connection->executeStatement('SET search_path TO public');
        self::assertSame(1, $count);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeFilenamesProvider(): iterable
    {
        yield 'non-ASCII filename' => ['café.png'];
        yield '%-containing filename' => ['photo%20mine.png'];
        yield 'path-like filename' => ['evil/name.png'];
    }

    #[DataProvider('unsafeFilenamesProvider')]
    public function testUploadingAPhotoWithAnUnsafeFilenameThenFetchingItDoesNotThrow(string $originalName): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'gebruiker@acme.test', 'correct-password');
        $this->login('acme', 'gebruiker@acme.test', 'correct-password');

        $contents = $this->onePixelPng();
        $uploadedFile = $this->makeUploadedFile($contents, $originalName, 'image/png');

        $this->client->request('POST', self::PHOTO_URL, server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN], files: ['photo' => $uploadedFile]);
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', self::PHOTO_URL, server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN]);

        self::assertResponseIsSuccessful();
        self::assertSame($contents, $this->client->getResponse()->getContent());
        self::assertNotNull($this->client->getResponse()->headers->get('Content-Disposition'));
    }

    /**
     * The core cross-user isolation claim: a second tenant user on the same
     * tenant never sees the first user's photo — GetProfilePhoto is always
     * scoped to the authenticated user's own id, never a client-supplied
     * one.
     */
    public function testATenantUserCannotSeeAnotherTenantUsersPhoto(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'eerste@acme.test', 'correct-password');
        $this->createTenantUser('acme', 'tweede@acme.test', 'correct-password');

        $this->login('acme', 'eerste@acme.test', 'correct-password');
        $uploadedFile = $this->makeUploadedFile($this->onePixelPng(), 'avatar.png', 'image/png');
        $this->client->request('POST', self::PHOTO_URL, server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN], files: ['photo' => $uploadedFile]);
        self::assertResponseStatusCodeSame(201);

        $this->login('acme', 'tweede@acme.test', 'correct-password');
        $this->client->request('GET', self::PHOTO_URL, server: ['HTTP_HOST' => 'acme.' . self::BASE_DOMAIN]);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * The tenant-isolation claim across tenants (mirroring
     * TenantOwnUsersApiTest::testATenantAdminCanNeverCreateAUserInAnotherTenant):
     * a token issued on one tenant's subdomain grants no access to a
     * different tenant's profile-photo endpoint at all — the request never
     * even resolves a TenantUser there.
     */
    public function testATokenIssuedOnOneTenantGrantsNoAccessOnAnotherTenant(): void
    {
        $this->provisionTenant('acme');
        $this->provisionTenant('beta');
        $this->createTenantUser('acme', 'gebruiker@acme.test', 'correct-password');
        $this->login('acme', 'gebruiker@acme.test', 'correct-password');
        $token = $this->responseCookie()?->getValue();
        self::assertNotNull($token);

        $this->client->request('GET', self::PHOTO_URL, server: [
            'HTTP_HOST' => 'beta.' . self::BASE_DOMAIN,
            'HTTP_COOKIE' => TenantApiTokenCookie::NAME . '=' . $token,
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testTheEndpointIsNotReachableOnTheBareMainDomain(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'gebruiker@acme.test', 'correct-password');
        $this->login('acme', 'gebruiker@acme.test', 'correct-password');
        $token = $this->responseCookie()?->getValue();

        $this->client->request('GET', self::PHOTO_URL, server: [
            'HTTP_HOST' => self::BASE_DOMAIN,
            'HTTP_COOKIE' => TenantApiTokenCookie::NAME . '=' . $token,
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheEndpointIsNotReachableOnTheAdminSubdomain(): void
    {
        $this->provisionTenant('acme');
        $this->createTenantUser('acme', 'gebruiker@acme.test', 'correct-password');
        $this->login('acme', 'gebruiker@acme.test', 'correct-password');
        $token = $this->responseCookie()?->getValue();

        $this->client->request('GET', self::PHOTO_URL, server: [
            'HTTP_HOST' => 'admin.' . self::BASE_DOMAIN,
            'HTTP_COOKIE' => TenantApiTokenCookie::NAME . '=' . $token,
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    private function onePixelPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        );
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

        static::getContainer()->get(CreateTenantUser::class)($email, $password, [TenantUser::DEFAULT_ROLE]);

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

    private function makeUploadedFile(string $contents, string $originalName, string $mimeType): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'koz33-upload-');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $originalName, $mimeType, null, true);
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
