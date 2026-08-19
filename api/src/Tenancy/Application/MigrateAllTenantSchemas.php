<?php

namespace App\Tenancy\Application;

use App\Tenancy\Domain\TenantRepositoryInterface;
use App\Tenancy\Infrastructure\TenantSchemaMigrator;

/**
 * Migrates every registered tenant's schema to the latest tenant migration
 * version. Used to roll out a schema change across all tenants at once
 * (`tenant:migrate --all`).
 *
 * A failure migrating one tenant does not stop the others from being
 * attempted — each tenant's outcome is collected and reported back, so a
 * single broken schema doesn't block a fleet-wide rollout.
 */
final class MigrateAllTenantSchemas
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly TenantSchemaMigrator $schemaMigrator,
    ) {
    }

    /** @return MigrateAllTenantSchemasResult */
    public function __invoke(): MigrateAllTenantSchemasResult
    {
        $succeeded = [];
        $failed = [];

        foreach ($this->tenantRepository->findAll() as $tenant) {
            $schemaName = $tenant->getSchemaName();

            try {
                $this->schemaMigrator->migrateToLatest($schemaName);
                $succeeded[] = $schemaName;
            } catch (\Throwable $exception) {
                $failed[$schemaName] = $exception->getMessage();
            }
        }

        return new MigrateAllTenantSchemasResult($succeeded, $failed);
    }
}
