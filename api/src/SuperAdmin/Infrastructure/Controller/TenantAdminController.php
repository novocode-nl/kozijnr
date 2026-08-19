<?php

namespace App\SuperAdmin\Infrastructure\Controller;

use App\SuperAdmin\Application\ListTenants;
use App\SuperAdmin\Application\TenantSummary;
use App\Tenancy\Application\ProvisionTenant;
use App\Tenancy\Domain\Exception\InvalidTenantNameException;
use App\Tenancy\Domain\Exception\SchemaAlreadyExistsException;
use App\Tenancy\Domain\Exception\TenantAlreadyExistsException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Super-admin tenant management API (KOZ-8): list existing tenants and
 * create new ones. Both routes sit under /api/admin, guarded by
 * SuperAdminRouteGuardListener (unreachable from a tenant subdomain) and
 * the `super_admin` firewall's ROLE_SUPER_ADMIN access control (see
 * config/packages/security.yaml) — #[IsGranted] here is a second,
 * defense-in-depth check at the controller level.
 *
 * Creation calls App\Tenancy\Application\ProvisionTenant (KOZ-7) directly —
 * tenant creation is Tenancy bounded-context logic, not SuperAdmin's, so no
 * SuperAdmin-owned wrapper use case exists for it. This controller only
 * adapts the returned Tenant to the SuperAdmin-specific TenantSummary read
 * model, same as ListTenants already does for the list endpoint.
 *
 * Create + list only, deliberately — schema deletion/archiving, billing,
 * and tenant-specific settings are out of scope for this ticket.
 */
#[IsGranted('ROLE_SUPER_ADMIN')]
final class TenantAdminController
{
    public function __construct(
        private readonly ListTenants $listTenants,
        private readonly ProvisionTenant $provisionTenant,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/admin/tenants', name: 'super_admin_tenants_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $summaries = ($this->listTenants)();

        return new JsonResponse(array_map($this->toArray(...), $summaries));
    }

    #[Route('/api/admin/tenants', name: 'super_admin_tenants_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
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
            $this->logger->error('Super-admin tenant creation failed for "{name}": {message}', [
                'name' => $name,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return new JsonResponse(['message' => 'Failed to create tenant.'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse($this->toArray(TenantSummary::fromTenant($tenant)), JsonResponse::HTTP_CREATED);
    }

    /** @return array{subdomain: string, createdAt: string} */
    private function toArray(TenantSummary $summary): array
    {
        return [
            'subdomain' => $summary->subdomain,
            'createdAt' => $summary->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
