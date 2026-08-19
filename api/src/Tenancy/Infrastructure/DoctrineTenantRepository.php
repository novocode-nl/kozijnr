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
}
