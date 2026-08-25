<?php

namespace App\TenantUser\Application;

use App\Tenancy\Domain\Exception\TenantNotFoundException;
use App\Tenancy\Domain\TenantRepositoryInterface;
use App\Tenancy\Domain\TenantSchemaContextInterface;

/**
 * Creates an *additional* tenant-user account inside one already-existing
 * tenant's schema (KOZ-31), analogous to the `tenant-user:create` CLI
 * command (App\TenantUser\Infrastructure\Command\CreateTenantUserCommand)
 * but reachable as an HTTP use case for the admin "Gebruikers" tab. Looks
 * the tenant up by subdomain in the public schema, switches search_path
 * into its tenant schema for the duration of the write, then always resets
 * back to `public` — same approach ListTenantUsers and
 * ProvisionTenantWithAdmin already use for the same schema switch.
 *
 * KOZ-31 rework: the validation + actual write is delegated to
 * CreateTenantUserForCurrentTenant, shared with the tenant-own
 * self-service flow — this class's only remaining job is resolving
 * "subdomain" -> "the right schema" before delegating, which the
 * self-service flow doesn't need at all (its schema is already right, from
 * the logged-in tenant-user's own session/Host header, never a
 * client-supplied subdomain).
 */
final class CreateTenantUserForTenant
{
    public function __construct(
        private readonly TenantSchemaContextInterface $schemaContext,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly CreateTenantUserForCurrentTenant $createTenantUserForCurrentTenant,
    ) {
    }

    public function __invoke(string $subdomain, string $email, string $role): CreatedTenantUserForTenant
    {
        $this->schemaContext->resetToPublic();

        $tenant = $this->tenantRepository->findBySubdomain($subdomain);
        if ($tenant === null) {
            throw TenantNotFoundException::forSubdomain($subdomain);
        }

        return $this->schemaContext->runInSchema(
            $tenant->getSchemaName(),
            fn () => ($this->createTenantUserForCurrentTenant)($email, $role),
        );
    }
}
