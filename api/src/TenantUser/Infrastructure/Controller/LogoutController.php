<?php

namespace App\TenantUser\Infrastructure\Controller;

use App\TenantUser\Application\LogoutTenantUser;
use App\TenantUser\Infrastructure\Security\TenantApiTokenCookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * POST /api/logout (KOZ-15): revokes the bearer token the caller
 * authenticated this very request with, so it can never be used again —
 * the tenant-user counterpart to /api/login. Only reachable on an
 * actually-resolved tenant subdomain
 * (App\TenantUser\Infrastructure\TenantRouteGuardListener) and, like
 * /api/me, requires a currently-valid token (the `tenant_users` firewall's
 * access_token authenticator, now reading the HttpOnly cookie via
 * CookieAccessTokenExtractor — KOZ-13 rework) — there is nothing to revoke
 * without one.
 *
 * Re-reads the cookie itself (rather than trying to recover the plaintext
 * token from the Security token storage, which only ever holds the
 * resolved TenantUser, not the credential it was resolved from) — the same
 * cookie the firewall's access_token authenticator already read to
 * authenticate this request. Also clears the cookie on the response: just
 * deleting the token row server-side would leave a stale, now-useless
 * cookie sitting in the browser.
 */
final class LogoutController
{
    public function __construct(private readonly LogoutTenantUser $logoutTenantUser)
    {
    }

    #[Route('/api/logout', name: 'tenant_logout', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(Request $request): JsonResponse
    {
        $token = (string) $request->cookies->get(TenantApiTokenCookie::NAME, '');

        ($this->logoutTenantUser)($token);

        $response = new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
        $response->headers->setCookie(TenantApiTokenCookie::clear());

        return $response;
    }
}
