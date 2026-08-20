<?php

namespace App\TenantUser\Infrastructure\Controller;

use App\TenantUser\Application\LogoutTenantUser;
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
 * /api/me, requires a currently-valid `Authorization: Bearer <token>`
 * (the `tenant_users` firewall's access_token authenticator) — there is
 * nothing to revoke without one.
 *
 * Re-reads the Authorization header itself (rather than trying to recover
 * the plaintext token from the Security token storage, which only ever
 * holds the resolved TenantUser, not the credential it was resolved from)
 * — the same header the firewall's access_token authenticator already
 * parsed to authenticate this request.
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
        $authorizationHeader = $request->headers->get('Authorization', '');
        $token = str_starts_with($authorizationHeader, 'Bearer ') ? substr($authorizationHeader, 7) : '';

        ($this->logoutTenantUser)($token);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
