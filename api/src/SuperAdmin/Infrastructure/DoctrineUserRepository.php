<?php

namespace App\SuperAdmin\Infrastructure;

use App\SuperAdmin\Domain\User;
use App\SuperAdmin\Domain\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findByEmail(string $email): ?User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    public function add(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
