<?php

namespace App\User\Infrastructure\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Route placeholder for the admin `json_login` firewall listener. This
 * action's body is never reached in normal operation: the `super_admin`
 * firewall's authenticator intercepts requests to this exact path before
 * the controller runs (see AuthenticationSuccessHandler /
 * AuthenticationFailureHandler). A route still has to exist here so the
 * router itself doesn't 404 the request before the firewall intercepts it.
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
