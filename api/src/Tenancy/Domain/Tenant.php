<?php

namespace App\Tenancy\Domain;

use App\Shared\Domain\Exception\ValidationException;
use Doctrine\ORM\Mapping as ORM;

/**
 * A tenant maps a subdomain to the Postgres schema that holds its data.
 * Rows live in the public schema — this is the one lookup that must always
 * happen against `public`, before any per-request schema switch occurs.
 */
#[ORM\Entity]
#[ORM\Table(name: 'tenants')]
#[ORM\UniqueConstraint(name: 'uniq_tenants_subdomain', columns: ['subdomain'])]
#[ORM\UniqueConstraint(name: 'uniq_tenants_schema_name', columns: ['schema_name'])]
class Tenant
{
    private const MAX_NAME_LENGTH = 255;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Free-text display name (e.g. "Acme B.V."), distinct from the
     * subdomain: the subdomain is a URL-safe slug, the name is whatever a
     * super admin wants to call the tenant.
     */
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 63)]
    private string $subdomain;

    #[ORM\Column(name: 'schema_name', type: 'string', length: 63)]
    private string $schemaName;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * Set when the tenant has been archived (soft-deleted): `null` means
     * active. An archived tenant is kept around (never hard-deleted) but
     * becomes structurally unreachable — see TenantResolverListener, which
     * treats an archived tenant's subdomain exactly like an unknown one.
     */
    #[ORM\Column(name: 'archived_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    public function __construct(
        string $name,
        string $subdomain,
        string $schemaName,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $archivedAt = null,
    ) {
        $name = trim($name);
        $subdomain = trim($subdomain);
        $schemaName = trim($schemaName);

        if ($name === '') {
            throw ValidationException::create('Tenant name cannot be empty.', 'tenants.error.nameRequired');
        }

        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw ValidationException::create(
                sprintf('Tenant name cannot be longer than %d characters.', self::MAX_NAME_LENGTH),
                'tenants.error.nameTooLong',
                ['max' => self::MAX_NAME_LENGTH],
            );
        }

        if ($subdomain === '') {
            throw ValidationException::create('Tenant subdomain cannot be empty.', 'tenants.error.subdomainRequired');
        }

        if ($schemaName === '') {
            // Internal/derived value, never entered directly by a user — no
            // error key, this is not a user-facing validation message.
            throw new \InvalidArgumentException('Tenant schema name cannot be empty.');
        }

        $this->name = $name;
        $this->subdomain = $subdomain;
        $this->schemaName = $schemaName;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->archivedAt = $archivedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSubdomain(): string
    {
        return $this->subdomain;
    }

    public function getSchemaName(): string
    {
        return $this->schemaName;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    /**
     * Updates the tenant's display name and subdomain. The Postgres schema
     * name is deliberately left untouched — it's an internal storage
     * detail, not part of the tenant's public identity, so an update never
     * needs to move any data.
     */
    public function updateDetails(string $name, string $subdomain): void
    {
        $name = trim($name);
        $subdomain = trim($subdomain);

        if ($name === '') {
            throw ValidationException::create('Tenant name cannot be empty.', 'tenants.error.nameRequired');
        }

        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw ValidationException::create(
                sprintf('Tenant name cannot be longer than %d characters.', self::MAX_NAME_LENGTH),
                'tenants.error.nameTooLong',
                ['max' => self::MAX_NAME_LENGTH],
            );
        }

        if ($subdomain === '') {
            throw ValidationException::create('Tenant subdomain cannot be empty.', 'tenants.error.subdomainRequired');
        }

        $this->name = $name;
        $this->subdomain = $subdomain;
    }

    /** Idempotent: archiving an already-archived tenant is a no-op. */
    public function archive(?\DateTimeImmutable $archivedAt = null): void
    {
        if ($this->isArchived()) {
            return;
        }

        $this->archivedAt = $archivedAt ?? new \DateTimeImmutable();
    }

    /** Idempotent: unarchiving an already-active tenant is a no-op. */
    public function unarchive(): void
    {
        $this->archivedAt = null;
    }
}
