<?php

namespace App\Tests\Shared\Domain\Storage;

use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Storage\StoredImageErrorKeys;
use App\Shared\Domain\Storage\StoredImagePolicy;
use PHPUnit\Framework\TestCase;

final class StoredImagePolicyTest extends TestCase
{
    private static function profilePhotoKeys(): StoredImageErrorKeys
    {
        return new StoredImageErrorKeys(
            'profilePhoto.error.unsupportedMimeType',
            'profilePhoto.error.empty',
            'profilePhoto.error.tooLarge',
        );
    }

    private static function loginImageKeys(): StoredImageErrorKeys
    {
        return new StoredImageErrorKeys(
            'tenantSettings.error.unsupportedMimeType',
            'tenantSettings.error.empty',
            'tenantSettings.error.tooLarge',
        );
    }

    public function testAcceptsAValidImage(): void
    {
        $this->expectNotToPerformAssertions();

        StoredImagePolicy::assertValid('image/png', 'binary', 'profile photo', self::profilePhotoKeys());
    }

    public function testRejectsAnUnsupportedMimeTypeWithTheCallersKey(): void
    {
        try {
            StoredImagePolicy::assertValid('application/x-php', 'binary', 'profile photo', self::profilePhotoKeys());
            self::fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            self::assertSame('Unsupported profile photo mime type "application/x-php".', $exception->getMessage());
            self::assertSame('profilePhoto.error.unsupportedMimeType', $exception->getErrorKey());
            self::assertSame(['mimeType' => 'application/x-php'], $exception->getErrorKeyParams());
        }
    }

    public function testRejectsAnEmptyFileWithUcfirstSubject(): void
    {
        try {
            StoredImagePolicy::assertValid('image/jpeg', '', 'login image', self::loginImageKeys());
            self::fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            self::assertSame('Login image file is empty.', $exception->getMessage());
            self::assertSame('tenantSettings.error.empty', $exception->getErrorKey());
        }
    }

    public function testRejectsAFileOverTheLimitWithTheCallersKey(): void
    {
        try {
            StoredImagePolicy::assertValid('image/jpeg', str_repeat('a', 6 * 1024 * 1024), 'login image', self::loginImageKeys());
            self::fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            self::assertSame('Login image exceeds the maximum size of 5242880 bytes.', $exception->getMessage());
            self::assertSame('tenantSettings.error.tooLarge', $exception->getErrorKey());
            self::assertSame(['maxSizeInBytes' => 5242880], $exception->getErrorKeyParams());
        }
    }

    public function testBuildsAStorageKeyWithOwnerDirectoryAndMimeExtension(): void
    {
        $key = StoredImagePolicy::storageKey('profile-photos', 7, 'image/png');

        self::assertMatchesRegularExpression('#^profile-photos/7/[0-9a-f]{32}\.png$#', $key);
    }

    public function testResolvesTheMimeTypeFromAStorageKeyExtension(): void
    {
        self::assertSame('image/webp', StoredImagePolicy::mimeTypeForStorageKey('tenant-login-images/7/abc.webp'));
        self::assertSame('image/jpeg', StoredImagePolicy::mimeTypeForStorageKey('x/y/photo.JPG'));
        self::assertSame('application/octet-stream', StoredImagePolicy::mimeTypeForStorageKey('x/y/file.bin'));
    }
}
