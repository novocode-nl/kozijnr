<?php

namespace App\SuperAdmin\Infrastructure\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Route placeholders for the super-admin `json_login` / `logout` firewall
 * listeners configured in config/packages/security.yaml.
 *
 * Neither action body is ever reached in normal operation: the
 * `super_admin` firewall's authenticator/logout listener intercepts
 * requests to these exact paths before the controller runs and returns its
 * own JSON response (see SuperAdminAuthenticationSuccessHandler /
 * SuperAdminAuthenticationFailureHandler / SuperAdminLogoutSuccessHandler).
 * A route still has to exist here so the router itself doesn't 404 the
 * request before the firewall gets a chance to intercept it.
 */
final class SuperAdminAuthController
{
    #[Route('/api/admin/login', name: 'super_admin_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // Unreachable when the super_admin firewall's json_login is
        // configured correctly — see class docblock.
        return new JsonResponse(['message' => 'Not authenticated.'], JsonResponse::HTTP_UNAUTHORIZED);
    }

    #[Route('/api/admin/logout', name: 'super_admin_logout', methods: ['POST'])]
    public function logout(): never
    {
        // Unreachable — intercepted by the firewall's logout listener.
        // See class docblock.
        throw new \LogicException('This should never be reached — the logout listener intercepts it first.');
    }
}
