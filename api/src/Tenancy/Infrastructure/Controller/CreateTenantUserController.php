<?php

namespace App\Tenancy\Infrastructure\Controller;

use App\Shared\Infrastructure\Http\ExceptionResponsePayload;
use App\Tenancy\Domain\Exception\TenantNotFoundException;
use App\TenantUser\Application\CreateTenantUserForTenant;
use App\TenantUser\Domain\Exception\TenantUserAlreadyExistsException;
use App\TenantUser\Domain\TenantUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin "create an additional tenant user" API (KOZ-31), backing the
 * "Gebruiker toevoegen" action on the tenant detail page's "Gebruikers"
 * tab. Route sits under /api/admin, guarded by AdminRouteGuardListener,
 * same as the sibling tenant controllers — even though the write itself
 * lands in the tenant's own schema (CreateTenantUserForTenant switches
 * search_path internally).
 *
 * Authorization is permission-based (`tenant:users:create`, checked via
 * PermissionVoter) — a distinct permission from `tenant:users:list` so
 * listing and creating can be granted independently.
 */
final class CreateTenantUserController
{
    public function __construct(private readonly CreateTenantUserForTenant $createTenantUserForTenant)
    {
    }

    #[Route('/api/admin/tenants/{subdomain}/users', name: 'admin_tenants_users_create', methods: ['POST'])]
    #[IsGranted('tenant:users:create')]
    public function __invoke(string $subdomain, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $email = is_array($payload) && isset($payload['email']) ? (string) $payload['email'] : '';
        $role = is_array($payload) && isset($payload['role']) && (string) $payload['role'] !== ''
            ? (string) $payload['role']
            : TenantUser::DEFAULT_ROLE;

        try {
            $result = ($this->createTenantUserForTenant)($subdomain, $email, $role);
        } catch (TenantNotFoundException $exception) {
            return new JsonResponse(ExceptionResponsePayload::for($exception), JsonResponse::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException|TenantUserAlreadyExistsException $exception) {
            return new JsonResponse(ExceptionResponsePayload::for($exception), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse($result->toArray(), JsonResponse::HTTP_CREATED);
    }
}
