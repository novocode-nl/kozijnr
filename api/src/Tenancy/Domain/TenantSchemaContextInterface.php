<?php

namespace App\Tenancy\Domain;

/**
 * The one place that flips the Doctrine connection's search_path into a
 * tenant schema and — crucially — always flips it back. Every Application/
 * console call site that used to inline the SET search_path / try / finally
 * dance goes through here, so the reset-to-public guarantee is enforced by
 * construction instead of by copy-paste discipline.
 *
 * Deliberately NOT used by TenantResolverListener (its switch must outlive
 * the method for the rest of the request) or TenantSchemaMigrator (sets the
 * bare schema without ", public" so the migration metadata table lands in
 * the tenant schema) — those two remain the documented, bespoke owners of
 * their own switching.
 */
interface TenantSchemaContextInterface
{
    public function resetToPublic(): void;

    /**
     * Runs $fn with search_path set to "<schema>, public", resetting to
     * public afterwards even when $fn throws.
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public function runInSchema(string $schemaName, callable $fn): mixed;
}
