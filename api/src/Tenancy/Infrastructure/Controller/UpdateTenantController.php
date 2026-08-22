<?php

namespace App\Tenancy\Infrastructure\Controller;

use App\Tenancy\Application\RenameTenant;
use App\Tenancy\Application\TenantSummary;
use App\Tenancy\Domain\Exception\InvalidTenantNameException;
use App\Tenancy\Domain\Exception\TenantAlreadyExistsException;
use App\Tenancy\Domain\Exception\TenantNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin tenant edit API: renames an existing tenant's subdomain. Route
 * sits under /api/admin, guarded by AdminRouteGuardListener (unreachable
 * from a tenant subdomain), same as CreateTenantController/
 * ListTenantsController.
 *
 * Authorization is permission-based (`tenant:update`, checked via
 * PermissionVoter), not role-name-based — same reasoning as the sibling
 * controllers.
 */
final class UpdateTenantController
{
    public function __construct(
        private readonly RenameTenant $renameTenant,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/admin/tenants/{subdomain}', name: 'admin_tenants_update', methods: ['PATCH'])]
    #[IsGranted('tenant:update')]
    public function __invoke(string $subdomain, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $name = is_array($payload) && isset($payload['name']) ? (string) $payload['name'] : '';

        try {
            $tenant = ($this->renameTenant)($subdomain, $name);
        } catch (TenantNotFoundException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], JsonResponse::HTTP_NOT_FOUND);
        } catch (InvalidTenantNameException|TenantAlreadyExistsException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $exception) {
            // Report cleanly rather than leaking a stack trace, but log the real cause.
            $this->logger->error('Admin tenant update failed for "{subdomain}": {message}', [
                'subdomain' => $subdomain,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return new JsonResponse(['message' => 'Failed to update tenant.'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(TenantSummary::fromTenant($tenant)->toArray(), JsonResponse::HTTP_OK);
    }
}
