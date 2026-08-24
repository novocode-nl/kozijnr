<?php

namespace App\Tenancy\Infrastructure\Controller;

use App\Shared\Infrastructure\Http\ExceptionResponsePayload;
use App\Tenancy\Domain\Exception\TenantNotFoundException;
use App\TenantUser\Application\ListTenantUsers;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin tenant-users list API, backing the "Gebruikers" tab on a tenant's
 * detail page. Route sits under /api/admin, guarded by
 * AdminRouteGuardListener, same as the sibling tenant controllers — even
 * though the data it reads lives inside the tenant's own schema
 * (ListTenantUsers switches search_path internally).
 *
 * Authorization is permission-based (`tenant:users:list`, checked via
 * PermissionVoter).
 */
final class ListTenantUsersController
{
    public function __construct(private readonly ListTenantUsers $listTenantUsers)
    {
    }

    #[Route('/api/admin/tenants/{subdomain}/users', name: 'admin_tenants_users_list', methods: ['GET'])]
    #[IsGranted('tenant:users:list')]
    public function __invoke(string $subdomain): JsonResponse
    {
        try {
            $summaries = ($this->listTenantUsers)($subdomain);
        } catch (TenantNotFoundException $exception) {
            return new JsonResponse(ExceptionResponsePayload::for($exception), JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse(array_map(static fn ($summary) => $summary->toArray(), $summaries));
    }
}
