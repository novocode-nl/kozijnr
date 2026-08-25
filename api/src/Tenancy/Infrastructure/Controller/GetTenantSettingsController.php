<?php

namespace App\Tenancy\Infrastructure\Controller;

use App\Tenancy\Application\TenantSettings;
use App\Tenancy\Domain\Tenant;
use App\Tenancy\Infrastructure\TenantResolverListener;
use App\TenantUser\Domain\TenantUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * GET /api/settings: the current tenant's settings (KOZ-34) — default
 * locale and whether a login image has been uploaded — for the tenant
 * settings page. ROLE_TENANT_ADMIN only, mirroring
 * CreateOwnTenantUserController's role gate (TenantUser has no
 * Role/Permission entity model of its own, see that class's doc).
 *
 * The tenant comes exclusively from TenantResolverListener's request
 * attribute (set purely from the request's Host header), never a
 * client-supplied subdomain — same reasoning as every other tenant-own
 * self-service endpoint (CreateOwnTenantUserController, ListOwnTenantUsersController).
 */
final class GetTenantSettingsController
{
    #[Route('/api/settings', name: 'tenant_settings_get', methods: ['GET'])]
    #[IsGranted(TenantUser::ROLE_TENANT_ADMIN)]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get(TenantResolverListener::REQUEST_ATTRIBUTE);

        return new JsonResponse(TenantSettings::fromTenant($tenant)->toArray(), JsonResponse::HTTP_OK);
    }
}
