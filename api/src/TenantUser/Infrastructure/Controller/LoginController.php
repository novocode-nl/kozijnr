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
 * POST /api/login: tenant-user login. Only reachable on an
 * actually-resolved tenant subdomain (TenantRouteGuardListener 404s it
 * elsewhere). On success, issues a fresh bearer token as an HttpOnly
 * cookie rather than in the JSON body, so client-side JS never sees the
 * token value; the browser sends the cookie automatically on subsequent
 * requests for the `tenant_users` firewall to resolve.
 *
 * Deliberately a plain controller calling the Application use case
 * directly, not routed through Symfony Security's json_login (unlike the
 * super-admin login): that mechanism issues a session, whereas this needs
 * a tenant-bound bearer token issued and persisted as part of the login.
 */
final class LoginController
{
    public function __construct(
        private readonly LoginTenantUser $loginTenantUser,
        private readonly LoggerInterface $logger,
        private readonly string $baseDomain,
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
        $response->headers->setCookie(TenantApiTokenCookie::issue($token, $this->baseDomain));

        return $response;
    }
}
