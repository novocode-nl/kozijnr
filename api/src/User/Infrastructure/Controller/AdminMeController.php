<?php

namespace App\User\Infrastructure\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * GET /api/admin/me: the whoami endpoint the frontend's proxy.ts guard
 * needs to turn "there is a PHPSESSID cookie" into "there is a *valid*
 * super-admin session" before letting a request through to an admin route.
 * Only reachable on the admin subdomain and requires the `super_admin`
 * firewall's session.
 *
 * The only caller only ever inspects the HTTP status code, never the
 * response body, so the body is deliberately empty — `#[IsGranted]` already
 * does the actual work, and the 200 response itself is the signal.
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
