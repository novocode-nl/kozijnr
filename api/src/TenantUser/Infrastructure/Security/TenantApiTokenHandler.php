<?php

namespace App\TenantUser\Infrastructure\Security;

use App\TenantUser\Domain\TenantApiToken;
use App\TenantUser\Domain\TenantApiTokenRepositoryInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Resolves an already-extracted bearer token to a TenantUser for the
 * `tenant_users` firewall. Looks the token up only via
 * TenantApiTokenRepositoryInterface, which (through the current
 * connection's search_path) only ever sees rows in the tenant schema the
 * *current* request resolved to — a token issued on tenant A's subdomain
 * finds nothing when search_path is set to tenant B's schema, rather than
 * "finds it but rejects it for the wrong tenant". This is what makes the
 * token tenant-bound.
 *
 * An unknown, expired, or revoked token all raise the exact same
 * BadCredentialsException with the exact same message, so a caller can
 * never distinguish the three. On every successful validation the token's
 * expiry is renewed (sliding expiry).
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
