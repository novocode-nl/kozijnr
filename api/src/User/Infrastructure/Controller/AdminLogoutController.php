<?php

namespace App\User\Infrastructure\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Route placeholder for the admin `logout` firewall listener configured in
 * config/packages/security.yaml. Lives in App\User (rework, KOZ-8): logging
 * out ends a User's authenticated session, this bounded context's own
 * concept — "super admin" is only a role on that User, not a reason for a
 * separate context. Split from AuthController in KOZ-10 to keep one
 * controller class per route/action (login and logout are two distinct
 * actions, not one).
 *
 * This action's body is never reached in normal operation: the
 * `super_admin` firewall's logout listener intercepts requests to this
 * exact path before the controller runs and returns its own JSON response
 * (see LogoutSuccessHandler). A route still has to exist here so the
 * router itself doesn't 404 the request before the firewall gets a chance
 * to intercept it.
 */
final class AdminLogoutController
{
    #[Route('/api/admin/logout', name: 'admin_logout', methods: ['POST'])]
    public function __invoke(): never
    {
        // Unreachable — intercepted by the firewall's logout listener.
        // See class docblock.
        throw new \LogicException('This should never be reached — the logout listener intercepts it first.');
    }
}
