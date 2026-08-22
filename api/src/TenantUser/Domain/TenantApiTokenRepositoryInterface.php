<?php

namespace App\TenantUser\Domain;

interface TenantApiTokenRepositoryInterface
{
    /** Looks up a token by the SHA-256 hash of its plaintext value, within the current tenant schema only. */
    public function findByTokenHash(string $tokenHash): ?TenantApiToken;

    public function add(TenantApiToken $token): void;

    /** Persists in-place changes to an already-issued token — used for sliding-expiry renewal. */
    public function save(TenantApiToken $token): void;

    /** Revokes a token: removes it so it can never be validated again. */
    public function remove(TenantApiToken $token): void;
}
