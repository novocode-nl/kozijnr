<?php

namespace App\Tests\ProfilePhoto\Application;

use App\ProfilePhoto\Application\UploadProfilePhoto;
use App\ProfilePhoto\Domain\ProfilePhoto;
use App\ProfilePhoto\Domain\ProfilePhotoRepositoryInterface;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Storage\FileStorageInterface;
use PHPUnit\Framework\TestCase;

final class UploadProfilePhotoTest extends TestCase
{
    private FileStorageInterface&\PHPUnit\Framework\MockObject\MockObject $storage;
    private ProfilePhotoRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repository;
    private UploadProfilePhoto $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = $this->createMock(FileStorageInterface::class);
        $this->repository = $this->createMock(ProfilePhotoRepositoryInterface::class);
        $this->handler = new UploadProfilePhoto($this->storage, $this->repository);
    }

    public function testAValidUploadIsStoredAndPersisted(): void
    {
        $this->repository->method('findByOwnerId')->willReturn(null);

        $this->storage->expects(self::once())
            ->method('write')
            ->with(self::matchesRegularExpression('#^profile-photos/42/[a-f0-9]{32}\.jpg$#'), 'binary-content');

        $this->repository->expects(self::once())
            ->method('add')
            ->with(self::isInstanceOf(ProfilePhoto::class));

        $photo = ($this->handler)(42, 'me.jpg', 'image/jpeg', 'binary-content');

        self::assertSame(42, $photo->getOwnerId());
        self::assertSame('image/jpeg', $photo->getMimeType());
        self::assertSame('me.jpg', $photo->getOriginalFilename());
        self::assertSame(strlen('binary-content'), $photo->getSizeInBytes());
    }

    public function testAnUnsupportedMimeTypeIsRejectedBeforeTouchingStorage(): void
    {
        $this->storage->expects(self::never())->method('write');
        $this->repository->expects(self::never())->method('add');

        $this->expectException(ValidationException::class);

        ($this->handler)(42, 'shell.php', 'application/x-php', 'contents');
    }

    public function testAnEmptyFileIsRejected(): void
    {
        $this->storage->expects(self::never())->method('write');

        $this->expectException(ValidationException::class);

        ($this->handler)(42, 'empty.jpg', 'image/jpeg', '');
    }

    public function testAFileLargerThanTheLimitIsRejected(): void
    {
        $this->storage->expects(self::never())->method('write');

        $this->expectException(ValidationException::class);

        ($this->handler)(42, 'huge.jpg', 'image/jpeg', str_repeat('a', 6 * 1024 * 1024));
    }

    public function testUploadingAgainReplacesTheExistingPhotoAndDeletesTheOldStoredFile(): void
    {
        $existing = ProfilePhoto::uploadedFor(42, 'profile-photos/42/old.jpg', 'image/jpeg', 10, 'old.jpg');
        $this->repository->method('findByOwnerId')->willReturn($existing);

        $this->storage->expects(self::once())->method('delete')->with('profile-photos/42/old.jpg');
        $this->repository->expects(self::once())->method('remove')->with($existing);
        $this->repository->expects(self::once())->method('add');

        ($this->handler)(42, 'new.jpg', 'image/png', 'new-content');
    }
}
