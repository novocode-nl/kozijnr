<?php

namespace App\TenantUser\Application;

use App\TenantUser\Domain\TenantApiToken;
use App\TenantUser\Domain\TenantApiTokenRepositoryInterface;

/**
 * Revokes a tenant-user's bearer token (KOZ-15): the counterpart to
 * LoginTenantUser. Removing the row is enough — TenantApiTokenHandler only
 * ever accepts a token it can look up by hash, so once the row is gone the
 * plaintext token can never authenticate again.
 *
 * Deliberately a no-op, not an error, when the token can't be found: by the
 * time this runs the caller has already passed the `tenant_users` firewall
 * with this exact token, so it existed a moment ago; if it's already gone
 * (e.g. a concurrent logout) there's simply nothing left to revoke.
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
