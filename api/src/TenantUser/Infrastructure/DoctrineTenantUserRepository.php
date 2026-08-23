<?php

namespace App\TenantUser\Infrastructure;

use App\TenantUser\Domain\TenantUser;
use App\TenantUser\Domain\TenantUserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineTenantUserRepository implements TenantUserRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findByEmail(string $email): ?TenantUser
    {
        return $this->entityManager->getRepository(TenantUser::class)->findOneBy(['email' => $email]);
    }

    public function findAll(): array
    {
        return $this->entityManager->getRepository(TenantUser::class)->findBy([], ['id' => 'ASC']);
    }

    public function add(TenantUser $tenantUser): void
    {
        $this->entityManager->persist($tenantUser);
        $this->entityManager->flush();
    }
}
