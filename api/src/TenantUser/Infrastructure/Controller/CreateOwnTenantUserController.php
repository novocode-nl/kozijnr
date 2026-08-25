<?php

namespace App\TenantUser\Infrastructure\Controller;

use App\Shared\Infrastructure\Http\ExceptionResponsePayload;
use App\TenantUser\Application\CreateTenantUserForCurrentTenant;
use App\TenantUser\Domain\Exception\TenantUserAlreadyExistsException;
use App\TenantUser\Domain\TenantUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Tenant-own self-service "create an additional tenant user" API (KOZ-31
 * rework), backing the "Gebruiker toevoegen" action on the tenant's own
 * "Gebruikers" page ({tenant}.<domein>/users) — the same ability
 * CreateTenantUserController already gives a super admin from
 * admin.<domein>, now also reachable by a logged-in tenant-admin acting on
 * their own tenant, without ever going through the admin realm.
 *
 * Only reachable on an actually-resolved tenant subdomain
 * (TenantRouteGuardListener 404s it elsewhere), same as /api/login and
 * /api/me. Deliberately takes no subdomain — unlike the admin route, the
 * tenant context here comes exclusively from the authenticated
 * TenantUser's own session (search_path is already pointed at their
 * tenant's schema by TenantResolverListener, purely from the request's
 * Host header), so there is no client-supplied value that could ever steer
 * this at a different tenant.
 *
 * Authorization is role-based (`ROLE_TENANT_ADMIN`) rather than the admin
 * route's fine-grained `tenant:users:create` *permission*: TenantUser
 * deliberately has no Role/Permission entity model of its own (see
 * TenantUser's class doc), only the two plain role strings that already
 * exist — ROLE_TENANT_ADMIN is the tenant-side equivalent of "may manage
 * this tenant's users" today.
 */
final class CreateOwnTenantUserController
{
    public function __construct(private readonly CreateTenantUserForCurrentTenant $createTenantUserForCurrentTenant)
    {
    }

    #[Route('/api/users', name: 'tenant_users_create', methods: ['POST'])]
    #[IsGranted(TenantUser::ROLE_TENANT_ADMIN)]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $email = is_array($payload) && isset($payload['email']) ? (string) $payload['email'] : '';
        $role = is_array($payload) && isset($payload['role']) && (string) $payload['role'] !== ''
            ? (string) $payload['role']
            : TenantUser::DEFAULT_ROLE;

        try {
            $result = ($this->createTenantUserForCurrentTenant)($email, $role);
        } catch (\InvalidArgumentException|TenantUserAlreadyExistsException $exception) {
            return new JsonResponse(ExceptionResponsePayload::for($exception), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse($result->toArray(), JsonResponse::HTTP_CREATED);
    }
}
