<?php

namespace App\Tenancy\Infrastructure\Controller;

use App\Shared\Infrastructure\Http\ExceptionResponsePayload;
use App\Tenancy\Application\ProvisionTenantWithAdmin;
use App\Tenancy\Application\TenantSummary;
use App\Tenancy\Domain\Exception\SchemaAlreadyExistsException;
use App\Tenancy\Domain\Exception\TenantAlreadyExistsException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin tenant creation API. Route sits under /api/admin, guarded by
 * AdminRouteGuardListener (unreachable from a tenant subdomain).
 *
 * Delegates to ProvisionTenantWithAdmin, which provisions the tenant and
 * creates a tenant-admin account for it at the caller-supplied `adminEmail`
 * (KOZ-27 rework), with a generated password. The credentials are included
 * in the response — this is the only time the plain-text password is ever
 * available, since only its hash is persisted.
 *
 * Authorization is permission-based (`tenant:create`, checked via
 * PermissionVoter), not role-name-based — security.yaml's access_control
 * still requires the request to be authenticated at all before reaching
 * here, but what an authenticated admin may then do is permission-based.
 */
final class CreateTenantController
{
    public function __construct(
        private readonly ProvisionTenantWithAdmin $provisionTenantWithAdmin,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/admin/tenants', name: 'admin_tenants_create', methods: ['POST'])]
    #[IsGranted('tenant:create')]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $name = is_array($payload) && isset($payload['name']) ? (string) $payload['name'] : '';
        $slug = is_array($payload) && isset($payload['slug']) ? (string) $payload['slug'] : '';
        $adminEmail = is_array($payload) && isset($payload['adminEmail']) ? (string) $payload['adminEmail'] : '';

        try {
            $result = ($this->provisionTenantWithAdmin)($name, $slug, $adminEmail);
        } catch (\InvalidArgumentException|TenantAlreadyExistsException|SchemaAlreadyExistsException $exception) {
            return new JsonResponse(ExceptionResponsePayload::for($exception), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $exception) {
            // Report cleanly rather than leaking a stack trace, but log the real cause.
            $this->logger->error('Admin tenant creation failed for "{name}": {message}', [
                'name' => $name,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return new JsonResponse(
                ExceptionResponsePayload::withKey('Failed to create tenant.', 'tenants.error.createFailed'),
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return new JsonResponse([
            ...TenantSummary::fromTenant($result->tenant)->toArray(),
            'tenantAdmin' => [
                'email' => $result->tenantAdminEmail,
                'password' => $result->tenantAdminPassword,
            ],
        ], JsonResponse::HTTP_CREATED);
    }
}
