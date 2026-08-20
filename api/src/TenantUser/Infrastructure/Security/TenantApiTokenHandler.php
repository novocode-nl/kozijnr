<?php

namespace App\TenantUser\Infrastructure\Security;

use App\TenantUser\Domain\TenantApiToken;
use App\TenantUser\Domain\TenantApiTokenRepositoryInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Resolves an `Authorization: Bearer <token>` header to a TenantUser for
 * the `tenant_users` firewall (config/packages/security.yaml).
 *
 * Looks the token up only via TenantApiTokenRepositoryInterface, which
 * (through the current Doctrine connection's search_path,
 * App\Tenancy\Infrastructure\TenantResolverListener) only ever sees rows in
 * the tenant schema the *current* request resolved to. A token issued on
 * tenant A's subdomain is a row that lives exclusively in tenant A's
 * schema, so looking it up while search_path is set to tenant B's schema
 * finds nothing — not "finds it but rejects it for the wrong tenant". This
 * is what makes the token tenant-bound (KOZ-11 DoD), the same structural
 * approach KOZ-6/7 use for tenant data isolation in general.
 *
 * KOZ-15: an unknown token, an expired token, and a revoked token (which is
 * simply a row that no longer exists — see App\TenantUser\Application\LogoutTenantUser)
 * all raise the exact same BadCredentialsException with the exact same
 * message here, on purpose — a caller must never be able to distinguish
 * "never existed" from "expired" from "revoked" (KOZ-15 DoD, the same
 * generic-error principle KOZ-11 applies to login). On every successful
 * validation the token's expiry is renewed (sliding expiry, KOZ-15) so an
 * actively used token effectively never expires.
 */
final class TenantApiTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(private readonly TenantApiTokenRepositoryInterface $tokenRepository)
    {
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        $token = $this->tokenRepository->findByTokenHash(TenantApiToken::hashFor($accessToken));

        $now = new \DateTimeImmutable();
        if ($token === null || $token->isExpired($now)) {
            throw new BadCredentialsException('Invalid or expired token.');
        }

        $token->renewExpiry($now);
        $this->tokenRepository->save($token);

        $tenantUser = $token->getTenantUser();

        return new UserBadge($tenantUser->getUserIdentifier(), static fn (): object => $tenantUser);
    }
}
