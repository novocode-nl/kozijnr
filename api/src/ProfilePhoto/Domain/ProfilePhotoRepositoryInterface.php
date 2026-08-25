<?php

namespace App\ProfilePhoto\Domain;

interface ProfilePhotoRepositoryInterface
{
    public function findByOwnerId(int $ownerId): ?ProfilePhoto;

    public function add(ProfilePhoto $profilePhoto): void;

    public function remove(ProfilePhoto $profilePhoto): void;
}
