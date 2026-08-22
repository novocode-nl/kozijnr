<?php

namespace App\Tenancy\Application;

use App\Tenancy\Domain\Tenant;

/**
 * Read-model DTO for the admin tenant list/detail views — nothing from
 * `Tenant`'s internals such as the Postgres schema name leaks through this
 * boundary.
 */
final class TenantSummary
{
    public function __construct(
        public readonly string $name,
        public readonly string $subdomain,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?\DateTimeImmutable $archivedAt,
    ) {
    }

    public static function fromTenant(Tenant $tenant): self
    {
        return new self($tenant->getName(), $tenant->getSubdomain(), $tenant->getCreatedAt(), $tenant->getArchivedAt());
    }

    /**
     * The JSON shape shared by the tenant admin controllers.
     *
     * @return array{name: string, subdomain: string, createdAt: string, archived: bool, archivedAt: ?string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'subdomain' => $this->subdomain,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'archived' => $this->archivedAt !== null,
            'archivedAt' => $this->archivedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
