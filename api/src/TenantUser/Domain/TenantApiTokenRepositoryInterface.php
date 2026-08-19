<?php

namespace App\TenantUser\Domain;

interface TenantApiTokenRepositoryInterface
{
    /**
     * Looks up a token by the SHA-256 hash of its plaintext value, within
     * the current tenant schema only (see TenantApiToken's docblock for why
     * that alone makes this tenant-bound).
     */
    public function findByTokenHash(string $tokenHash): ?TenantApiToken;

    public function add(TenantApiToken $token): void;
}
