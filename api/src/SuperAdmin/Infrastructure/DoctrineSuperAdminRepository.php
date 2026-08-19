<?php

namespace App\SuperAdmin\Infrastructure;

use App\SuperAdmin\Domain\SuperAdmin;
use App\SuperAdmin\Domain\SuperAdminRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineSuperAdminRepository implements SuperAdminRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findByEmail(string $email): ?SuperAdmin
    {
        return $this->entityManager->getRepository(SuperAdmin::class)->findOneBy(['email' => $email]);
    }

    public function add(SuperAdmin $superAdmin): void
    {
        $this->entityManager->persist($superAdmin);
        $this->entityManager->flush();
    }
}
