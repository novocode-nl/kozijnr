<?php

namespace App\Tenancy\Infrastructure\Controller;

use App\Tenancy\Application\ProvisionTenant;
use App\Tenancy\Application\TenantSummary;
use App\Tenancy\Domain\Exception\InvalidTenantNameException;
use App\Tenancy\Domain\Exception\SchemaAlreadyExistsException;
use App\Tenancy\Domain\Exception\TenantAlreadyExistsException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin tenant creation API (KOZ-8, split from TenantAdminController in
 * KOZ-10 to keep one controller class per route/action): creates a new
 * tenant. Lives in App\Tenancy (rework, KOZ-8) rather than a separate
 * "SuperAdmin" context — creating a tenant is Tenancy business, not a
 * domain concept of its own; only who is *allowed* to call it is an
 * authorization concern, handled below via #[IsGranted] plus the
 * `super_admin` firewall (see config/packages/security.yaml) as defense in
 * depth, not via a dedicated bounded context. The route sits under
 * /api/admin, guarded by AdminRouteGuardListener (unreachable from a
 * tenant subdomain).
 *
 * Authorization here is permission-based, not role-name-based (KOZ-9): this
 * action requires its own fine-grained permission (`tenant:create`),
 * checked via App\User\Infrastructure\Security\PermissionVoter against the
 * authenticated User's assigned roles/permissions, rather than a blanket
 * #[IsGranted('ROLE_SUPER_ADMIN')]. security.yaml's access_control still
 * requires the request to be authenticated at all (IS_AUTHENTICATED_FULLY)
 * before reaching here — the ROLE_SUPER_ADMIN role itself is still what
 * login/logout gate on the firewall, since authentication (who can log in
 * at all) stays role-based by design; only what an authenticated admin may
 * then *do* is permission-based.
 *
 * Calls this context's own ProvisionTenant (KOZ-7) directly. Schema
 * deletion/archiving, billing, and tenant-specific settings are out of
 * scope for this action, deliberately.
 */
final class CreateTenantController
{
    public function __construct(
        private readonly ProvisionTenant $provisionTenant,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/admin/tenants', name: 'admin_tenants_create', methods: ['POST'])]
    #[IsGranted('tenant:create')]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $name = is_array($payload) && isset($payload['name']) ? (string) $payload['name'] : '';

        try {
            $tenant = ($this->provisionTenant)($name);
        } catch (InvalidTenantNameException|TenantAlreadyExistsException|SchemaAlreadyExistsException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $exception) {
            // Same reasoning as ProvisionTenantCommand: report cleanly
            // rather than leaking a stack trace, but log the real cause.
            $this->logger->error('Admin tenant creation failed for "{name}": {message}', [
                'name' => $name,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return new JsonResponse(['message' => 'Failed to create tenant.'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(TenantSummary::fromTenant($tenant)->toArray(), JsonResponse::HTTP_CREATED);
    }
}
