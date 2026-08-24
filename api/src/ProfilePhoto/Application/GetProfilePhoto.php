<?php

namespace App\ProfilePhoto\Application;

use App\ProfilePhoto\Domain\ProfilePhotoRepositoryInterface;
use App\Shared\Domain\Storage\FileStorageInterface;

/**
 * Query handler (CQRS — zero side effects): fetches an owner's profile
 * photo metadata from the repository and its binary content from the
 * storage port, so the presentation layer can serve it back — see KOZ-32
 * DoD "upload -> opslag -> metadata in DB -> terug op te vragen".
 */
final class GetProfilePhoto
{
    public function __construct(
        private readonly FileStorageInterface $storage,
        private readonly ProfilePhotoRepositoryInterface $repository,
    ) {
    }

    public function __invoke(int $ownerId): ?ProfilePhotoContent
    {
        $photo = $this->repository->findByOwnerId($ownerId);
        if ($photo === null) {
            return null;
        }

        return new ProfilePhotoContent(
            $this->storage->read($photo->getStorageKey()),
            $photo->getMimeType(),
            $photo->getOriginalFilename(),
        );
    }
}
