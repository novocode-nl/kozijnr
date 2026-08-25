<?php

namespace App\TenantUser\Infrastructure\Controller;

use App\TenantUser\Application\ListTenantUsersForCurrentTenant;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Tenant-own self-service users list (KOZ-31 rework), backing the
 * "Gebruikers" page on the tenant's own environment
 * ({tenant}.<domein>/users). Any authenticated tenant user may see their
 * own tenant's colleagues (only the "Gebruiker toevoegen" action itself is
 * restricted to ROLE_TENANT_ADMIN — see CreateOwnTenantUserController).
 *
 * Only reachable on an actually-resolved tenant subdomain
 * (TenantRouteGuardListener 404s it elsewhere). Takes no subdomain — the
 * tenant context comes exclusively from the authenticated session, never a
 * client-supplied value.
 */
final class ListOwnTenantUsersController
{
    public function __construct(private readonly ListTenantUsersForCurrentTenant $listTenantUsersForCurrentTenant)
    {
    }

    #[Route('/api/users', name: 'tenant_users_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(): JsonResponse
    {
        $summaries = ($this->listTenantUsersForCurrentTenant)();

        return new JsonResponse(array_map(static fn ($summary) => $summary->toArray(), $summaries));
    }
}
