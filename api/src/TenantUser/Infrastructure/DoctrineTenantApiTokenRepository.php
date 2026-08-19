<?php

namespace App\TenantUser\Infrastructure;

use App\TenantUser\Domain\TenantApiToken;
use App\TenantUser\Domain\TenantApiTokenRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineTenantApiTokenRepository implements TenantApiTokenRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findByTokenHash(string $tokenHash): ?TenantApiToken
    {
        return $this->entityManager->getRepository(TenantApiToken::class)->findOneBy(['tokenHash' => $tokenHash]);
    }

    public function add(TenantApiToken $token): void
    {
        $this->entityManager->persist($token);
        $this->entityManager->flush();
    }
}
