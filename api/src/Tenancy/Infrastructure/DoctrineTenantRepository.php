<?php

namespace App\Tenancy\Infrastructure;

use App\Tenancy\Domain\Tenant;
use App\Tenancy\Domain\TenantRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineTenantRepository implements TenantRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findBySubdomain(string $subdomain): ?Tenant
    {
        return $this->entityManager->getRepository(Tenant::class)->findOneBy(['subdomain' => $subdomain]);
    }

    public function findBySchemaName(string $schemaName): ?Tenant
    {
        return $this->entityManager->getRepository(Tenant::class)->findOneBy(['schemaName' => $schemaName]);
    }

    public function findAll(): array
    {
        return $this->entityManager->getRepository(Tenant::class)->findAll();
    }

    public function findAllActive(): array
    {
        return $this->entityManager->getRepository(Tenant::class)->findBy(['archivedAt' => null]);
    }

    public function findAllArchived(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Tenant::class, 't')
            ->where('t.archivedAt IS NOT NULL')
            ->getQuery()
            ->getResult();
    }

    public function add(Tenant $tenant): void
    {
        $this->entityManager->persist($tenant);
        $this->entityManager->flush();
    }

    public function update(Tenant $tenant): void
    {
        $this->entityManager->flush();
    }
}
