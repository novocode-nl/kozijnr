<?php

namespace App\Tenancy\Domain;

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
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 63)]
    private string $subdomain;

    #[ORM\Column(name: 'schema_name', type: 'string', length: 63)]
    private string $schemaName;

    public function __construct(string $subdomain, string $schemaName)
    {
        $subdomain = trim($subdomain);
        $schemaName = trim($schemaName);

        if ($subdomain === '') {
            throw new \InvalidArgumentException('Tenant subdomain cannot be empty.');
        }

        if ($schemaName === '') {
            throw new \InvalidArgumentException('Tenant schema name cannot be empty.');
        }

        $this->subdomain = $subdomain;
        $this->schemaName = $schemaName;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubdomain(): string
    {
        return $this->subdomain;
    }

    public function getSchemaName(): string
    {
        return $this->schemaName;
    }
}
