<?php

namespace App\User\Infrastructure\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Route placeholder for the admin `json_login` firewall listener configured
 * in config/packages/security.yaml. Lives in App\User (rework, KOZ-8):
 * logging in authenticates a User, which is this bounded context's own
 * concept — "super admin" is only a role on that User, not a reason for a
 * separate context. Split from AuthController in KOZ-10 to keep one
 * controller class per route/action (login and logout are two distinct
 * actions, not one).
 *
 * This action's body is never reached in normal operation: the
 * `super_admin` firewall's authenticator intercepts requests to this exact
 * path before the controller runs and returns its own JSON response (see
 * AuthenticationSuccessHandler / AuthenticationFailureHandler). A route
 * still has to exist here so the router itself doesn't 404 the request
 * before the firewall gets a chance to intercept it.
 */
final class AdminLoginController
{
    #[Route('/api/admin/login', name: 'admin_login', methods: ['POST'])]
    public function __invoke(): JsonResponse
    {
        // Unreachable when the super_admin firewall's json_login is
        // configured correctly — see class docblock.
        return new JsonResponse(['message' => 'Not authenticated.'], JsonResponse::HTTP_UNAUTHORIZED);
    }
}
