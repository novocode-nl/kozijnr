<?php

namespace App\Tenancy\Application;

use App\Tenancy\Domain\Tenant;

/**
 * Read-model DTO for the admin tenant list — nothing from `Tenant`'s
 * internals such as the Postgres schema name leaks through this boundary.
 */
final class TenantSummary
{
    public function __construct(
        public readonly string $subdomain,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function fromTenant(Tenant $tenant): self
    {
        return new self($tenant->getSubdomain(), $tenant->getCreatedAt());
    }

    /**
     * The JSON shape shared by ListTenantsController and CreateTenantController.
     *
     * @return array{subdomain: string, createdAt: string}
     */
    public function toArray(): array
    {
        return [
            'subdomain' => $this->subdomain,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
