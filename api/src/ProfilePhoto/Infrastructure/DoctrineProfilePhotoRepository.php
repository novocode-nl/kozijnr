<?php

namespace App\ProfilePhoto\Infrastructure;

use App\ProfilePhoto\Domain\ProfilePhoto;
use App\ProfilePhoto\Domain\ProfilePhotoRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineProfilePhotoRepository implements ProfilePhotoRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findByOwnerId(int $ownerId): ?ProfilePhoto
    {
        return $this->entityManager->getRepository(ProfilePhoto::class)->findOneBy(['ownerId' => $ownerId]);
    }

    public function add(ProfilePhoto $profilePhoto): void
    {
        $this->entityManager->persist($profilePhoto);
        $this->entityManager->flush();
    }

    public function remove(ProfilePhoto $profilePhoto): void
    {
        $this->entityManager->remove($profilePhoto);
        $this->entityManager->flush();
    }
}
