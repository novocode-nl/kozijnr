<?php

namespace App\Tenancy\Domain\Exception;

use App\Shared\Domain\Exception\HasErrorKey;

/**
 * Raised when provisioning is attempted for a subdomain or schema name that
 * is already registered in the public `tenants` table.
 */
final class TenantAlreadyExistsException extends \RuntimeException implements HasErrorKey
{
    /** @param array<string, scalar> $errorKeyParams */
    private function __construct(
        string $message,
        private readonly string $errorKey,
        private readonly array $errorKeyParams,
    ) {
        parent::__construct($message);
    }

    public static function forSubdomain(string $subdomain): self
    {
        return new self(
            sprintf('A tenant with subdomain "%s" already exists.', $subdomain),
            'tenants.error.subdomainAlreadyExists',
            ['subdomain' => $subdomain],
        );
    }

    public static function forSchemaName(string $schemaName): self
    {
        return new self(
            sprintf('A tenant with schema "%s" already exists.', $schemaName),
            'tenants.error.schemaAlreadyExists',
            ['schema' => $schemaName],
        );
    }

    public function getErrorKey(): string
    {
        return $this->errorKey;
    }

    /** @return array<string, scalar> */
    public function getErrorKeyParams(): array
    {
        return $this->errorKeyParams;
    }
}
