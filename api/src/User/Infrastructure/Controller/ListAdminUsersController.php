<?php

namespace App\User\Infrastructure\Controller;

use App\User\Application\ListAdminUsers;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin user overview API (KOZ-30). Route sits under /api/admin, guarded by
 * AdminRouteGuardListener (unreachable from a tenant subdomain).
 *
 * Authorization is permission-based (`user:list`, checked via
 * PermissionVoter), same pattern as ListTenantsController.
 */
final class ListAdminUsersController
{
    public function __construct(private readonly ListAdminUsers $listAdminUsers)
    {
    }

    #[Route('/api/admin/users', name: 'admin_users_list', methods: ['GET'])]
    #[IsGranted('user:list')]
    public function __invoke(): JsonResponse
    {
        $summaries = ($this->listAdminUsers)();

        return new JsonResponse(array_map(static fn ($summary) => $summary->toArray(), $summaries));
    }
}
