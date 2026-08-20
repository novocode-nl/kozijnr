<?php

namespace App\User\Infrastructure\Controller;

use Symfony\Component\HttpFoundation\Response;
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
 *
 * KOZ-14 rework (round 4, non-blocking review finding): the only caller
 * (web/proxy.ts's hasValidAdminSession) only ever inspects the HTTP status
 * code — it never reads the response body. Returning the admin's email and
 * roles here was therefore unnecessary data exposure (low risk — it's the
 * logged-in admin's own data, and it never reached the client — but
 * pointless all the same). `#[IsGranted]` already does all the actual
 * work: the presence of a 200 response *is* the "yes, valid session"
 * signal, so the body can just be empty.
 */
final class AdminMeController
{
    #[Route('/api/admin/me', name: 'admin_me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(): Response
    {
        return new Response('', Response::HTTP_OK);
    }
}
