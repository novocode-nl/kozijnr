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
 * Admin tenant creation API. Route sits under /api/admin, guarded by
 * AdminRouteGuardListener (unreachable from a tenant subdomain).
 *
 * Authorization is permission-based (`tenant:create`, checked via
 * PermissionVoter), not role-name-based — security.yaml's access_control
 * still requires the request to be authenticated at all before reaching
 * here, but what an authenticated admin may then do is permission-based.
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
            // Report cleanly rather than leaking a stack trace, but log the real cause.
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
