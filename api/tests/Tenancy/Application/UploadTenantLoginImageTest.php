<?php

namespace App\Tests\Tenancy\Application;

use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Storage\FileStorageInterface;
use App\Tenancy\Application\UploadTenantLoginImage;
use App\Tenancy\Domain\Tenant;
use App\Tenancy\Domain\TenantRepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * KOZ-34: uploads a tenant's login-screen image. Deliberately mirrors
 * App\ProfilePhoto\Application\UploadProfilePhoto's validation (allowlist,
 * 5MB limit) and replace-not-version behaviour, applied to
 * `Tenant::$loginImageStorageKey` instead of a dedicated entity — see that
 * class for the reasoning this repeats.
 */
final class UploadTenantLoginImageTest extends TestCase
{
    private FileStorageInterface&\PHPUnit\Framework\MockObject\MockObject $storage;
    private TenantRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repository;
    private UploadTenantLoginImage $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = $this->createMock(FileStorageInterface::class);
        $this->repository = $this->createMock(TenantRepositoryInterface::class);
        $this->handler = new UploadTenantLoginImage($this->storage, $this->repository);
    }

    public function testAValidUploadIsStoredAndRecordedOnTheTenant(): void
    {
        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme');
        self::setTenantId($tenant, 7);

        $this->storage->expects(self::once())
            ->method('write')
            ->with(self::matchesRegularExpression('#^tenant-login-images/7/[a-f0-9]{32}\.jpg$#'), 'binary-content');

        $this->repository->expects(self::once())->method('update')->with($tenant);

        ($this->handler)($tenant, 'login.jpg', 'image/jpeg', 'binary-content');

        self::assertMatchesRegularExpression('#^tenant-login-images/7/[a-f0-9]{32}\.jpg$#', $tenant->getLoginImageStorageKey());
    }

    public function testAnUnsupportedMimeTypeIsRejectedBeforeTouchingStorage(): void
    {
        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme');

        $this->storage->expects(self::never())->method('write');
        $this->repository->expects(self::never())->method('update');

        try {
            ($this->handler)($tenant, 'shell.php', 'application/x-php', 'contents');
            self::fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            self::assertSame('Unsupported login image mime type "application/x-php".', $exception->getMessage());
            self::assertSame('tenantSettings.error.unsupportedMimeType', $exception->getErrorKey());
            self::assertSame(['mimeType' => 'application/x-php'], $exception->getErrorKeyParams());
        }
    }

    public function testAnEmptyFileIsRejected(): void
    {
        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme');

        $this->storage->expects(self::never())->method('write');

        try {
            ($this->handler)($tenant, 'empty.jpg', 'image/jpeg', '');
            self::fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            self::assertSame('Login image file is empty.', $exception->getMessage());
            self::assertSame('tenantSettings.error.empty', $exception->getErrorKey());
        }
    }

    public function testAFileLargerThanTheLimitIsRejected(): void
    {
        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme');

        $this->storage->expects(self::never())->method('write');

        try {
            ($this->handler)($tenant, 'huge.jpg', 'image/jpeg', str_repeat('a', 6 * 1024 * 1024));
            self::fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            self::assertSame('Login image exceeds the maximum size of 5242880 bytes.', $exception->getMessage());
            self::assertSame('tenantSettings.error.tooLarge', $exception->getErrorKey());
            self::assertSame(['maxSizeInBytes' => 5242880], $exception->getErrorKeyParams());
        }
    }

    public function testUploadingAgainReplacesTheExistingImageAndDeletesTheOldStoredFile(): void
    {
        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme', loginImageStorageKey: 'tenant-login-images/7/old.jpg');
        self::setTenantId($tenant, 7);

        $this->storage->expects(self::once())->method('delete')->with('tenant-login-images/7/old.jpg');
        $this->repository->expects(self::once())->method('update')->with($tenant);

        ($this->handler)($tenant, 'new.jpg', 'image/png', 'new-content');

        self::assertNotSame('tenant-login-images/7/old.jpg', $tenant->getLoginImageStorageKey());
    }

    private static function setTenantId(Tenant $tenant, int $id): void
    {
        $reflection = new \ReflectionProperty(Tenant::class, 'id');
        $reflection->setValue($tenant, $id);
    }
}
