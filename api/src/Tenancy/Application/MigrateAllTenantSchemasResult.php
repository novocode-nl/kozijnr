<?php

namespace App\Tenancy\Application;

/**
 * Outcome of a `tenant:migrate --all` run: which tenant schemas migrated
 * successfully, and which failed (schema name => error message).
 */
final class MigrateAllTenantSchemasResult
{
    /**
     * @param string[]              $succeeded schema names
     * @param array<string, string> $failed    schema name => error message
     */
    public function __construct(
        public readonly array $succeeded,
        public readonly array $failed,
    ) {
    }

    public function isFullySuccessful(): bool
    {
        return $this->failed === [];
    }
}
