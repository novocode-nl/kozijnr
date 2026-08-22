<?php

namespace App\User\Infrastructure\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Route placeholder for the admin `logout` firewall listener. This
 * action's body is never reached in normal operation: the `super_admin`
 * firewall's logout listener intercepts requests to this exact path before
 * the controller runs (see LogoutSuccessHandler). A route still has to
 * exist here so the router itself doesn't 404 the request first.
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
