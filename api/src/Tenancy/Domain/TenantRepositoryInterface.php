<?php

namespace App\Tenancy\Domain;

interface TenantRepositoryInterface
{
    /**
     * Looks up a tenant by its subdomain. Must always query the public
     * schema, regardless of what schema the connection's search_path is
     * currently pointed at.
     */
    public function findBySubdomain(string $subdomain): ?Tenant;

    /**
     * Looks up a tenant by its Postgres schema name. Same public-schema
     * guarantee as findBySubdomain().
     */
    public function findBySchemaName(string $schemaName): ?Tenant;

    /**
     * All registered tenants, used to migrate every tenant schema to the
     * latest tenant migration version (see `tenant:migrate --all`).
     *
     * @return Tenant[]
     */
    public function findAll(): array;

    /**
     * Registers a newly provisioned tenant in the public `tenants` table.
     */
    public function add(Tenant $tenant): void;
}
