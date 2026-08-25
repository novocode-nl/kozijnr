<?php

namespace App\ProfilePhoto\Application;

use App\ProfilePhoto\Domain\ProfilePhoto;
use App\ProfilePhoto\Domain\ProfilePhotoRepositoryInterface;
use App\Shared\Domain\Storage\FileStorageInterface;
use App\Shared\Domain\Storage\StoredImageErrorKeys;
use App\Shared\Domain\Storage\StoredImagePolicy;

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
    public function __construct(
        private readonly FileStorageInterface $storage,
        private readonly ProfilePhotoRepositoryInterface $repository,
    ) {
    }

    public function __invoke(int $ownerId, string $originalFilename, string $mimeType, string $contents): ProfilePhoto
    {
        StoredImagePolicy::assertValid($mimeType, $contents, 'profile photo', new StoredImageErrorKeys(
            'profilePhoto.error.unsupportedMimeType',
            'profilePhoto.error.empty',
            'profilePhoto.error.tooLarge',
        ));

        $sizeInBytes = strlen($contents);

        $existing = $this->repository->findByOwnerId($ownerId);
        if ($existing !== null) {
            $this->storage->delete($existing->getStorageKey());
            $this->repository->remove($existing);
        }

        $storageKey = StoredImagePolicy::storageKey('profile-photos', $ownerId, $mimeType);

        $this->storage->write($storageKey, $contents);

        $photo = ProfilePhoto::uploadedFor($ownerId, $storageKey, $mimeType, $sizeInBytes, $originalFilename);
        $this->repository->add($photo);

        return $photo;
    }
}
