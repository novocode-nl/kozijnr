<?php

namespace App\Tests\ProfilePhoto\Application;

use App\ProfilePhoto\Application\GetProfilePhoto;
use App\ProfilePhoto\Application\ProfilePhotoContent;
use App\ProfilePhoto\Domain\ProfilePhoto;
use App\ProfilePhoto\Domain\ProfilePhotoRepositoryInterface;
use App\Shared\Domain\Storage\FileStorageInterface;
use PHPUnit\Framework\TestCase;

/**
 * Query handler: read-only (CQRS — no side effects), reads metadata from
 * the repository and the binary content from the storage port, and hands
 * both back as one DTO for the controller to turn into an HTTP response.
 */
final class GetProfilePhotoTest extends TestCase
{
    public function testReturnsNullWhenTheOwnerHasNoProfilePhoto(): void
    {
        $repository = $this->createMock(ProfilePhotoRepositoryInterface::class);
        $repository->method('findByOwnerId')->willReturn(null);
        $storage = $this->createMock(FileStorageInterface::class);
        $storage->expects(self::never())->method('read');

        $handler = new GetProfilePhoto($storage, $repository);

        self::assertNull($handler(42));
    }

    public function testReturnsMetadataAndContentWhenAProfilePhotoExists(): void
    {
        $photo = ProfilePhoto::uploadedFor(42, 'profile-photos/42/abc.jpg', 'image/jpeg', 5, 'me.jpg');

        $repository = $this->createMock(ProfilePhotoRepositoryInterface::class);
        $repository->method('findByOwnerId')->with(42)->willReturn($photo);

        $storage = $this->createMock(FileStorageInterface::class);
        $storage->method('read')->with('profile-photos/42/abc.jpg')->willReturn('binary');

        $handler = new GetProfilePhoto($storage, $repository);

        $result = $handler(42);

        self::assertInstanceOf(ProfilePhotoContent::class, $result);
        self::assertSame('binary', $result->contents);
        self::assertSame('image/jpeg', $result->mimeType);
        self::assertSame('me.jpg', $result->originalFilename);
    }
}
