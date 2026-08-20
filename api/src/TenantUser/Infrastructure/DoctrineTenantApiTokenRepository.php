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

    public function save(TenantApiToken $token): void
    {
        // $token is already a managed entity (loaded via findByTokenHash),
        // so flush() alone persists whatever in-place changes were made to
        // it (e.g. renewExpiry() for KOZ-15 sliding expiry) — no explicit
        // persist() needed, unlike add().
        $this->entityManager->flush();
    }

    public function remove(TenantApiToken $token): void
    {
        $this->entityManager->remove($token);
        $this->entityManager->flush();
    }
}
