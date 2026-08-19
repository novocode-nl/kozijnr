<?php

namespace App\User\Infrastructure;

use App\User\Domain\Role;
use App\User\Domain\RoleRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineRoleRepository implements RoleRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findByName(string $name): ?Role
    {
        return $this->entityManager->getRepository(Role::class)->findOneBy(['name' => $name]);
    }
}
