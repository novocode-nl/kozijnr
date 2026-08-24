<?php

namespace App\ProfilePhoto\Application;

use App\ProfilePhoto\Domain\ProfilePhoto;
use App\ProfilePhoto\Domain\ProfilePhotoRepositoryInterface;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Storage\FileStorageInterface;

/**
 * Command handler: validates an uploaded profile photo (type, size — KOZ-32
 * kernpunt: validation belongs here, never in the storage adapter) and
 * stores it via the FileStorageInterface port, then persists its metadata.
 *
 * One photo per owner: an existing photo is fully replaced (old stored
 * file deleted, old metadata row removed) rather than kept as history —
 * profile photos have no versioning requirement, and keeping orphaned
 * files around would leak storage indefinitely.
 */
final class UploadProfilePhoto
{
    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private const MAX_SIZE_IN_BYTES = 5 * 1024 * 1024; // 5 MiB

    private const EXTENSIONS_BY_MIME_TYPE = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly FileStorageInterface $storage,
        private readonly ProfilePhotoRepositoryInterface $repository,
    ) {
    }

    public function __invoke(int $ownerId, string $originalFilename, string $mimeType, string $contents): ProfilePhoto
    {
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::create(
                sprintf('Unsupported profile photo mime type "%s".', $mimeType),
                'profilePhoto.error.unsupportedMimeType',
                ['mimeType' => $mimeType],
            );
        }

        $sizeInBytes = strlen($contents);

        if ($sizeInBytes === 0) {
            throw ValidationException::create(
                'Profile photo file is empty.',
                'profilePhoto.error.empty',
            );
        }

        if ($sizeInBytes > self::MAX_SIZE_IN_BYTES) {
            throw ValidationException::create(
                sprintf('Profile photo exceeds the maximum size of %d bytes.', self::MAX_SIZE_IN_BYTES),
                'profilePhoto.error.tooLarge',
                ['maxSizeInBytes' => self::MAX_SIZE_IN_BYTES],
            );
        }

        $existing = $this->repository->findByOwnerId($ownerId);
        if ($existing !== null) {
            $this->storage->delete($existing->getStorageKey());
            $this->repository->remove($existing);
        }

        $storageKey = sprintf(
            'profile-photos/%d/%s.%s',
            $ownerId,
            bin2hex(random_bytes(16)),
            self::EXTENSIONS_BY_MIME_TYPE[$mimeType],
        );

        $this->storage->write($storageKey, $contents);

        $photo = ProfilePhoto::uploadedFor($ownerId, $storageKey, $mimeType, $sizeInBytes, $originalFilename);
        $this->repository->add($photo);

        return $photo;
    }
}
