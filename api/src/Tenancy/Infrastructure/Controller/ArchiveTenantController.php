<?php

namespace App\Tenancy\Infrastructure\Controller;

use App\Shared\Infrastructure\Http\ExceptionResponsePayload;
use App\Tenancy\Application\ArchiveTenant;
use App\Tenancy\Application\TenantSummary;
use App\Tenancy\Domain\Exception\TenantNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin tenant archive API: soft-deletes a tenant so it becomes
 * structurally unreachable (including for login) without destroying any
 * data — see TenantResolverListener and ArchiveTenant. Route sits under
 * /api/admin, guarded by AdminRouteGuardListener, same as the sibling
 * tenant controllers.
 *
 * Authorization is permission-based (`tenant:archive`, checked via
 * PermissionVoter).
 */
final class ArchiveTenantController
{
    public function __construct(
        private readonly ArchiveTenant $archiveTenant,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/admin/tenants/{subdomain}/archive', name: 'admin_tenants_archive', methods: ['POST'])]
    #[IsGranted('tenant:archive')]
    public function __invoke(string $subdomain): JsonResponse
    {
        try {
            $tenant = ($this->archiveTenant)($subdomain);
        } catch (TenantNotFoundException $exception) {
            return new JsonResponse(ExceptionResponsePayload::for($exception), JsonResponse::HTTP_NOT_FOUND);
        } catch (\Throwable $exception) {
            $this->logger->error('Admin tenant archive failed for "{subdomain}": {message}', [
                'subdomain' => $subdomain,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return new JsonResponse(
                ExceptionResponsePayload::withKey('Failed to archive tenant.', 'error.generic.tenantArchiveFailed'),
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return new JsonResponse(TenantSummary::fromTenant($tenant)->toArray(), JsonResponse::HTTP_OK);
    }
}
