<?php

namespace App\TenantUser\Application;

use App\TenantUser\Domain\TenantApiToken;
use App\TenantUser\Domain\TenantApiTokenRepositoryInterface;

/**
 * Revokes a tenant-user's bearer token. Removing the row is enough —
 * TenantApiTokenHandler only accepts a token it can look up by hash.
 * A no-op, not an error, when the token can't be found (e.g. a concurrent
 * logout already removed it).
 */
final class LogoutTenantUser
{
    public function __construct(private readonly TenantApiTokenRepositoryInterface $tokenRepository)
    {
    }

    public function __invoke(string $plainTextToken): void
    {
        $token = $this->tokenRepository->findByTokenHash(TenantApiToken::hashFor($plainTextToken));

        if ($token === null) {
            return;
        }

        $this->tokenRepository->remove($token);
    }
}
