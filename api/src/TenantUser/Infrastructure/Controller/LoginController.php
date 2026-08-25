<?php

namespace App\TenantUser\Infrastructure\Controller;

use App\Shared\Infrastructure\Http\ExceptionResponsePayload;
use App\Tenancy\Domain\Tenant;
use App\Tenancy\Infrastructure\TenantResolverListener;
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
        } catch (InvalidCredentialsException $exception) {
            return new JsonResponse(ExceptionResponsePayload::for($exception), JsonResponse::HTTP_UNAUTHORIZED);
        } catch (\Throwable $exception) {
            $this->logger->error('Tenant login failed unexpectedly for "{email}": {message}', [
                'email' => $email,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return new JsonResponse(
                ExceptionResponsePayload::for(InvalidCredentialsException::create()),
                JsonResponse::HTTP_UNAUTHORIZED,
            );
        }

        /**
         * KOZ-34: tells the frontend which locale to start the app in
         * right after this login (App\Tenancy\Application\GetTenantLocale
         * isn't used here — the resolved Tenant is already in hand, and
         * this is a one-line read, not a use case in its own right).
         *
         * @var Tenant $tenant
         */
        $tenant = $request->attributes->get(TenantResolverListener::REQUEST_ATTRIBUTE);

        $response = new JsonResponse(
            ['message' => 'Logged in.', 'defaultLocale' => $tenant->getDefaultLocale()],
            JsonResponse::HTTP_OK,
        );
        $response->headers->setCookie(TenantApiTokenCookie::issue($token, $this->baseDomain));

        return $response;
    }
}
