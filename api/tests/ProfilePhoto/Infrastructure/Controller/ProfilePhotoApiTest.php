<?php

namespace App\Tests\ProfilePhoto\Infrastructure\Controller;

use App\User\Application\CreateSuperAdmin;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * End-to-end coverage of the KOZ-32 DoD: "upload -> opslag -> metadata in
 * DB -> terug op te vragen" for the concrete profile-photo feature, on top
 * of the local Flysystem adapter that is active in the test environment
 * (see .env.test's APP_STORAGE_LOCAL_ROOT).
 */
final class ProfilePhotoApiTest extends WebTestCase
{
    private const ADMIN_HOST = 'admin.localhost';
    private const UPLOAD_URL = '/api/admin/me/profile-photo';

    private KernelBrowser $client;
    private string $storageRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->storageRoot = self::getContainer()->getParameter('app.storage.local_root');

        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $connection->executeStatement('DELETE FROM public.profile_photos');
        $connection->executeStatement('DELETE FROM public.users');

        self::getContainer()->get(CreateSuperAdmin::class)('admin@kozijnr.nl', 'super-secret-123');
    }

    protected function tearDown(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $connection->executeStatement('DELETE FROM public.profile_photos');
        $connection->executeStatement('DELETE FROM public.users');

        $this->removeDirectory($this->storageRoot);

        parent::tearDown();
    }

    public function testUploadingWithoutASessionIsRejected(): void
    {
        $this->client->request('POST', self::UPLOAD_URL, server: ['HTTP_HOST' => self::ADMIN_HOST]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testFetchingWithoutASessionIsRejected(): void
    {
        $this->client->request('GET', self::UPLOAD_URL, server: ['HTTP_HOST' => self::ADMIN_HOST]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testFetchingWhenNoPhotoWasUploadedReturns404(): void
    {
        $this->login();

        $this->client->request('GET', self::UPLOAD_URL, server: ['HTTP_HOST' => self::ADMIN_HOST]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testUploadingAPhotoThenFetchingItRoundTrips(): void
    {
        $this->login();

        $contents = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        );
        $uploadedFile = $this->makeUploadedFile($contents, 'avatar.png', 'image/png');

        $this->client->request('POST', self::UPLOAD_URL, server: ['HTTP_HOST' => self::ADMIN_HOST], files: ['photo' => $uploadedFile]);

        self::assertResponseStatusCodeSame(201);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('image/png', $body['mimeType']);
        self::assertSame('avatar.png', $body['originalFilename']);
        self::assertSame(strlen($contents), $body['sizeInBytes']);
        self::assertArrayNotHasKey('storageKey', $body);

        $this->client->request('GET', self::UPLOAD_URL, server: ['HTTP_HOST' => self::ADMIN_HOST]);

        self::assertResponseIsSuccessful();
        self::assertSame('image/png', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertSame($contents, $this->client->getResponse()->getContent());
    }

    /**
     * KOZ-32 rework #3: regression test for the blocking review finding —
     * GetProfilePhotoController used to call
     * HeaderUtils::makeDisposition(DISPOSITION_INLINE, $originalFilename)
     * without an ASCII-only, '%'-free fallback (the third argument), so
     * Symfony fell back to originalFilename itself as the fallback. Since
     * originalFilename is the unsanitized, client-supplied name (only path
     * separators are stripped by UploadedFile::getName()), a non-ASCII
     * name, a '%'-containing name, or (defensively) a path-like name would
     * make HeaderUtils::makeDisposition() throw an uncaught
     * InvalidArgumentException, turning every subsequent GET into a 500 —
     * the photo would be permanently unfetchable. Covers all three
     * concrete cases confirmed during review: a non-ASCII filename, a
     * '%'-containing filename, and a path-like filename.
     *
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
        $this->login();

        $contents = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        );
        $uploadedFile = $this->makeUploadedFile($contents, $originalName, 'image/png');

        $this->client->request('POST', self::UPLOAD_URL, server: ['HTTP_HOST' => self::ADMIN_HOST], files: ['photo' => $uploadedFile]);
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', self::UPLOAD_URL, server: ['HTTP_HOST' => self::ADMIN_HOST]);

        self::assertResponseIsSuccessful();
        self::assertSame($contents, $this->client->getResponse()->getContent());
        self::assertNotNull($this->client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testUploadingAnUnsupportedFileTypeIsRejectedWithAnErrorKey(): void
    {
        $this->login();

        $uploadedFile = $this->makeUploadedFile('<?php echo "hi"; ?>', 'shell.php', 'application/x-php');

        $this->client->request('POST', self::UPLOAD_URL, server: ['HTTP_HOST' => self::ADMIN_HOST], files: ['photo' => $uploadedFile]);

        self::assertResponseStatusCodeSame(422);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('profilePhoto.error.unsupportedMimeType', $body['errorKey']);
    }

    /**
     * KOZ-32 rework: regression test for a previous review finding — a
     * file between the application's 5MB limit and PHP's configured
     * upload_max_filesize/post_max_size (6M/8M, see docker/backend/uploads.ini)
     * must reach UploadProfilePhoto's own size check and come back as
     * profilePhoto.error.tooLarge, not the generic profilePhoto.error.missingFile.
     *
     * Note: this only proves the *application's* 5MB limit is enforced
     * correctly for a file that would fit under php.ini's limits. It does
     * NOT exercise php.ini itself — WebTestCase/KernelBrowser injects an
     * UploadedFile directly and bypasses PHP's own upload handling
     * entirely (no real multipart POST goes through PHP's SAPI), so it
     * cannot prove that upload_max_filesize/post_max_size in
     * docker/backend/uploads.ini are actually applied by the running
     * container. That would require a smoke test against the real running
     * container (e.g. a curl-based multipart POST over the ini limit).
     */
    public function testUploadingAFileOverTheApplicationLimitButUnderThePhpIniLimitIsRejectedAsTooLarge(): void
    {
        $this->login();

        $pngHeader = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        );
        // 5.5MB: over UploadProfilePhoto::MAX_SIZE_IN_BYTES (5MB) but under
        // php.ini's upload_max_filesize (6M) / post_max_size (8M), so PHP
        // itself must accept the upload and hand it to the application.
        $oversizedContents = $pngHeader . str_repeat('0', (int) (5.5 * 1024 * 1024) - strlen($pngHeader));
        self::assertGreaterThan(5 * 1024 * 1024, strlen($oversizedContents));
        self::assertLessThan(6 * 1024 * 1024, strlen($oversizedContents));

        $uploadedFile = $this->makeUploadedFile($oversizedContents, 'oversized.png', 'image/png');

        $this->client->request('POST', self::UPLOAD_URL, server: ['HTTP_HOST' => self::ADMIN_HOST], files: ['photo' => $uploadedFile]);

        self::assertResponseStatusCodeSame(422);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('profilePhoto.error.tooLarge', $body['errorKey']);
    }

    public function testUploadingWithoutAFileIsRejected(): void
    {
        $this->login();

        $this->client->request('POST', self::UPLOAD_URL, server: ['HTTP_HOST' => self::ADMIN_HOST]);

        self::assertResponseStatusCodeSame(422);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('profilePhoto.error.missingFile', $body['errorKey']);
    }

    public function testUploadingASecondPhotoReplacesTheFirst(): void
    {
        $this->login();

        // Two distinct valid 1x1 images (a PNG and a JPEG) so the test can
        // tell which one is currently stored purely by their differing
        // bytes/mime-type.
        $pngContents = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        );
        $jpegContents = base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AVN//2Q==',
        );
        $first = $this->makeUploadedFile($pngContents, 'first.png', 'image/png');
        $this->client->request('POST', self::UPLOAD_URL, server: ['HTTP_HOST' => self::ADMIN_HOST], files: ['photo' => $first]);
        self::assertResponseStatusCodeSame(201);

        $second = $this->makeUploadedFile($jpegContents, 'second.jpg', 'image/jpeg');
        $this->client->request('POST', self::UPLOAD_URL, server: ['HTTP_HOST' => self::ADMIN_HOST], files: ['photo' => $second]);
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', self::UPLOAD_URL, server: ['HTTP_HOST' => self::ADMIN_HOST]);
        self::assertSame($jpegContents, $this->client->getResponse()->getContent());

        $connection = $this->connection();
        $count = (int) $connection->fetchOne('SELECT COUNT(*) FROM public.profile_photos');
        self::assertSame(1, $count);
    }

    private function login(): void
    {
        $this->client->request('POST', '/api/admin/login', server: [
            'HTTP_HOST' => self::ADMIN_HOST,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'admin@kozijnr.nl', 'password' => 'super-secret-123']));
        self::assertResponseIsSuccessful();
    }

    private function makeUploadedFile(string $contents, string $originalName, string $mimeType): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'koz32-upload-');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $originalName, $mimeType, null, true);
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
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
