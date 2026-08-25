<?php

namespace App\Tests\ProfilePhoto\Domain;

use App\ProfilePhoto\Domain\ProfilePhoto;
use PHPUnit\Framework\TestCase;

final class ProfilePhotoTest extends TestCase
{
    public function testUploadedForBuildsAPhotoWithTheGivenMetadata(): void
    {
        $photo = ProfilePhoto::uploadedFor(
            ownerId: 42,
            storageKey: 'profile-photos/42/ab12cd34.jpg',
            mimeType: 'image/jpeg',
            sizeInBytes: 12345,
            originalFilename: 'me.jpg',
        );

        self::assertSame(42, $photo->getOwnerId());
        self::assertSame('profile-photos/42/ab12cd34.jpg', $photo->getStorageKey());
        self::assertSame('image/jpeg', $photo->getMimeType());
        self::assertSame(12345, $photo->getSizeInBytes());
        self::assertSame('me.jpg', $photo->getOriginalFilename());
        self::assertInstanceOf(\DateTimeImmutable::class, $photo->getUploadedAt());
    }

    public function testOwnerIdMustBePositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ProfilePhoto::uploadedFor(0, 'k', 'image/jpeg', 1, 'a.jpg');
    }

    public function testStorageKeyCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ProfilePhoto::uploadedFor(1, '', 'image/jpeg', 1, 'a.jpg');
    }

    public function testMimeTypeCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ProfilePhoto::uploadedFor(1, 'k', '', 1, 'a.jpg');
    }

    public function testSizeInBytesMustBePositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ProfilePhoto::uploadedFor(1, 'k', 'image/jpeg', 0, 'a.jpg');
    }

    public function testOriginalFilenameCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ProfilePhoto::uploadedFor(1, 'k', 'image/jpeg', 1, '');
    }
}
