<?php

namespace App\TenantUser\Infrastructure\Controller;

use App\TenantUser\Application\LoginTenantUser;
use App\TenantUser\Domain\Exception\InvalidCredentialsException;
use App\TenantUser\Infrastructure\Security\TenantApiTokenCookie;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /api/login (KOZ-11): tenant-user login. Only reachable on an
 * actually-resolved tenant subdomain (App\TenantUser\Infrastructure\TenantRouteGuardListener
 * 404s it elsewhere). Validates email+password against the tenant users
 * living in the current tenant schema and, on success, issues a fresh
 * bearer token as an HttpOnly cookie (KOZ-13 rework — see
 * TenantApiTokenCookie) rather than in the JSON body: the whole point is
 * that client-side JS never gets to see the token value at all, so a
 * subsequent request only needs to include the cookie (which the browser
 * does automatically) for the `tenant_users` firewall's access_token
 * authenticator (config/packages/security.yaml) to resolve it via
 * CookieAccessTokenExtractor.
 *
 * Deliberately a plain controller calling the Application use case
 * directly, not routed through Symfony Security's json_login (unlike the
 * super-admin login, App\User\Infrastructure\Controller\AdminLoginController):
 * that mechanism issues a session, whereas this ticket's DoD needs a
 * tenant-bound bearer token issued and persisted as part of the login
 * itself.
 */
final class LoginController
{
    public function __construct(
        private readonly LoginTenantUser $loginTenantUser,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/login', name: 'tenant_login', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $email = is_array($payload) && isset($payload['email']) ? (string) $payload['email'] : '';
        $password = is_array($payload) && isset($payload['password']) ? (string) $payload['password'] : '';

        try {
            $token = ($this->loginTenantUser)($email, $password);
        } catch (InvalidCredentialsException) {
            // Same generic message regardless of what made the credentials
            // invalid (KOZ-11 DoD) — the exception itself already carries
            // no distinguishing detail, this just maps it to a response.
            return new JsonResponse(['message' => 'Invalid credentials.'], JsonResponse::HTTP_UNAUTHORIZED);
        } catch (\Throwable $exception) {
            $this->logger->error('Tenant login failed unexpectedly for "{email}": {message}', [
                'email' => $email,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return new JsonResponse(['message' => 'Invalid credentials.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $response = new JsonResponse(['message' => 'Logged in.'], JsonResponse::HTTP_OK);
        $response->headers->setCookie(TenantApiTokenCookie::issue($token));

        return $response;
    }
}
