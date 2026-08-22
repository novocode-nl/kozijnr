<?php

namespace App\Tenancy\Infrastructure\Controller;

use App\Tenancy\Application\ListTenants;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin tenant list API. Route sits under /api/admin, guarded by
 * AdminRouteGuardListener (unreachable from a tenant subdomain).
 *
 * Defaults to active tenants only; `?archived=true` switches to the
 * archived-only view the admin tenant overview's "show archived" toggle
 * uses.
 *
 * Authorization is permission-based (`tenant:list`, checked via
 * PermissionVoter), not role-name-based — see CreateTenantController for
 * the same reasoning.
 */
final class ListTenantsController
{
    public function __construct(private readonly ListTenants $listTenants)
    {
    }

    #[Route('/api/admin/tenants', name: 'admin_tenants_list', methods: ['GET'])]
    #[IsGranted('tenant:list')]
    public function __invoke(Request $request): JsonResponse
    {
        $includeArchived = $request->query->getBoolean('archived');

        $summaries = ($this->listTenants)($includeArchived);

        return new JsonResponse(array_map(static fn ($summary) => $summary->toArray(), $summaries));
    }
}
