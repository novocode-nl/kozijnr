<?php

namespace App\Tenancy\Infrastructure\Controller;

use App\Tenancy\Application\ListTenants;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin tenant list API (KOZ-8, split from TenantAdminController in
 * KOZ-10 to keep one controller class per route/action): lists existing
 * tenants. Lives in App\Tenancy (rework, KOZ-8) rather than a separate
 * "SuperAdmin" context — listing tenants is Tenancy business, not a domain
 * concept of its own; only who is *allowed* to call it is an authorization
 * concern, handled below via #[IsGranted] plus the `super_admin` firewall
 * (see config/packages/security.yaml) as defense in depth, not via a
 * dedicated bounded context. The route sits under /api/admin, guarded by
 * AdminRouteGuardListener (unreachable from a tenant subdomain).
 *
 * Authorization here is permission-based, not role-name-based (KOZ-9): this
 * action requires its own fine-grained permission (`tenant:list`), checked
 * via App\User\Infrastructure\Security\PermissionVoter against the
 * authenticated User's assigned roles/permissions, rather than a blanket
 * #[IsGranted('ROLE_SUPER_ADMIN')]. security.yaml's access_control still
 * requires the request to be authenticated at all (IS_AUTHENTICATED_FULLY)
 * before reaching here — the ROLE_SUPER_ADMIN role itself is still what
 * login/logout gate on the firewall, since authentication (who can log in
 * at all) stays role-based by design; only what an authenticated admin may
 * then *do* is permission-based.
 */
final class ListTenantsController
{
    public function __construct(private readonly ListTenants $listTenants)
    {
    }

    #[Route('/api/admin/tenants', name: 'admin_tenants_list', methods: ['GET'])]
    #[IsGranted('tenant:list')]
    public function __invoke(): JsonResponse
    {
        $summaries = ($this->listTenants)();

        return new JsonResponse(array_map(static fn ($summary) => $summary->toArray(), $summaries));
    }
}
