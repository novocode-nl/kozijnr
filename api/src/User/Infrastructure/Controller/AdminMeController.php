<?php

namespace App\User\Infrastructure\Controller;

use App\User\Domain\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * GET /api/admin/me (KOZ-14 rework): the whoami endpoint the frontend's
 * proxy.ts guard needs to turn "there is a PHPSESSID cookie" into "there
 * is a *valid* super-admin session" before letting a request through to
 * any admin.<domein> route — the admin-side counterpart to
 * App\TenantUser\Infrastructure\Controller\MeController, which already
 * does the same job for the tenant bearer-token cookie. Only reachable on
 * the admin subdomain (App\Tenancy\Infrastructure\AdminRouteGuardListener)
 * and requires the `super_admin` firewall's session
 * (config/packages/security.yaml's blanket `^/api/admin` ->
 * IS_AUTHENTICATED_FULLY access_control rule already covers this path, no
 * new entry needed there).
 *
 * Split from AdminLoginController/AdminLogoutController per KOZ-10's one
 * route/action per controller class rule — this is a third, distinct
 * action, not a second method bolted onto either of those.
 */
final class AdminMeController
{
    public function __construct(private readonly Security $security)
    {
    }

    #[Route('/api/admin/me', name: 'admin_me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(): JsonResponse
    {
        $user = $this->security->getUser();
        $email = $user instanceof User ? $user->getEmail() : $user?->getUserIdentifier();
        $roles = $user?->getRoles() ?? [];

        return new JsonResponse(['email' => $email, 'roles' => $roles], JsonResponse::HTTP_OK);
    }
}
