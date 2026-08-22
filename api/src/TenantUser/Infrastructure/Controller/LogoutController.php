<?php

namespace App\TenantUser\Infrastructure\Controller;

use App\TenantUser\Application\LogoutTenantUser;
use App\TenantUser\Infrastructure\Security\TenantApiTokenCookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * POST /api/logout: revokes the bearer token the caller authenticated this
 * request with. Re-reads the cookie itself, rather than trying to recover
 * the plaintext token from Security's token storage (which only holds the
 * resolved TenantUser, not the credential). Also clears the cookie on the
 * response — deleting the token row server-side alone would leave a
 * stale cookie sitting in the browser.
 */
final class LogoutController
{
    public function __construct(
        private readonly LogoutTenantUser $logoutTenantUser,
        private readonly string $baseDomain,
    ) {
    }

    #[Route('/api/logout', name: 'tenant_logout', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(Request $request): JsonResponse
    {
        $token = (string) $request->cookies->get(TenantApiTokenCookie::NAME, '');

        ($this->logoutTenantUser)($token);

        $response = new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
        $response->headers->setCookie(TenantApiTokenCookie::clear($this->baseDomain));

        return $response;
    }
}
