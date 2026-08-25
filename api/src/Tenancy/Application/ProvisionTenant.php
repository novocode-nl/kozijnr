<?php

namespace App\Tenancy\Application;

use App\Tenancy\Domain\Exception\SchemaAlreadyExistsException;
use App\Tenancy\Domain\Exception\TenantAlreadyExistsException;
use App\Tenancy\Domain\Tenant;
use App\Tenancy\Domain\TenantName;
use App\Tenancy\Domain\TenantRepositoryInterface;
use App\Tenancy\Domain\TenantSchemaContextInterface;
use App\Tenancy\Infrastructure\TenantSchemaManager;
use App\Tenancy\Infrastructure\TenantSchemaMigrator;

/**
 * Provisions a brand new tenant end to end: validates the name, creates its
 * Postgres schema, runs the tenant migration set on it, and registers it in
 * the public `tenants` table.
 *
 * Fails cleanly with no half-provisioned state:
 * - An invalid name, an already-used subdomain/schema, or a pre-existing
 *   raw Postgres schema is rejected before anything is created.
 * - If schema creation succeeds but a later step (migrations, registering
 *   the tenant) fails, the schema is dropped again before the exception
 *   propagates, so a failed run never leaves a half-migrated schema behind.
 */
final class ProvisionTenant
{
    public function __construct(
        private readonly TenantSchemaContextInterface $schemaContext,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly TenantSchemaManager $schemaManager,
        private readonly TenantSchemaMigrator $schemaMigrator,
    ) {
    }

    /**
     * @param string $name Display name for the tenant (free text).
     * @param string|null $slug Subdomain/schema-derivation slug, validated
     *   by TenantName. Defaults to `$name` when omitted, so console
     *   callers that only ever supplied one value (`tenant:provision
     *   <name>`) keep working unchanged.
     */
    public function __invoke(string $name, ?string $slug = null): Tenant
    {
        $tenantName = new TenantName($slug ?? $name);

        // Defensive reset, same reasoning as TenantResolverListener: never
        // assume the connection is already on `public`.
        $this->schemaContext->resetToPublic();

        $subdomain = $tenantName->asSubdomain();
        $schemaName = $tenantName->asSchemaName();

        if ($this->tenantRepository->findBySubdomain($subdomain) !== null) {
            throw TenantAlreadyExistsException::forSubdomain($subdomain);
        }

        if ($this->tenantRepository->findBySchemaName($schemaName) !== null) {
            throw TenantAlreadyExistsException::forSchemaName($schemaName);
        }

        if ($this->schemaManager->exists($schemaName)) {
            throw SchemaAlreadyExistsException::forSchemaName($schemaName);
        }

        $this->schemaManager->create($schemaName);

        try {
            $this->schemaMigrator->migrateToLatest($schemaName);

            $tenant = new Tenant($name, $subdomain, $schemaName);
            $this->tenantRepository->add($tenant);
        } catch (\Throwable $exception) {
            $this->schemaManager->drop($schemaName);

            throw $exception;
        }

        return $tenant;
    }
}
