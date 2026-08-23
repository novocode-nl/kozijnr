<?php

namespace App\Tenancy\Infrastructure\Controller;

use App\Shared\Infrastructure\Http\ExceptionResponsePayload;
use App\Tenancy\Application\TenantSummary;
use App\Tenancy\Application\UnarchiveTenant;
use App\Tenancy\Domain\Exception\TenantNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin tenant unarchive API: reverses ArchiveTenantController, making the
 * tenant reachable again and moving it back into the default (active)
 * admin tenant overview. Route sits under /api/admin, guarded by
 * AdminRouteGuardListener, same as the sibling tenant controllers.
 *
 * Authorization is permission-based (`tenant:archive`, checked via
 * PermissionVoter) — the same permission gates both directions of the
 * archive toggle.
 */
final class UnarchiveTenantController
{
    public function __construct(
        private readonly UnarchiveTenant $unarchiveTenant,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/admin/tenants/{subdomain}/unarchive', name: 'admin_tenants_unarchive', methods: ['POST'])]
    #[IsGranted('tenant:archive')]
    public function __invoke(string $subdomain): JsonResponse
    {
        try {
            $tenant = ($this->unarchiveTenant)($subdomain);
        } catch (TenantNotFoundException $exception) {
            return new JsonResponse(ExceptionResponsePayload::for($exception), JsonResponse::HTTP_NOT_FOUND);
        } catch (\Throwable $exception) {
            $this->logger->error('Admin tenant unarchive failed for "{subdomain}": {message}', [
                'subdomain' => $subdomain,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return new JsonResponse(
                ExceptionResponsePayload::withKey('Failed to unarchive tenant.', 'error.generic.tenantUnarchiveFailed'),
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return new JsonResponse(TenantSummary::fromTenant($tenant)->toArray(), JsonResponse::HTTP_OK);
    }
}
