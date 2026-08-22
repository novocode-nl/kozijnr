<?php

namespace App\User\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * A single fine-grained permission (e.g. `tenant:list`), assignable to one
 * or more Roles. Its own database table rather than a hardcoded string
 * constant, so new permissions can be introduced via seed data alone.
 */
#[ORM\Entity]
#[ORM\Table(name: 'permissions')]
#[ORM\UniqueConstraint(name: 'uniq_permissions_name', columns: ['name'])]
class Permission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    public function __construct(string $name)
    {
        $name = trim($name);

        if ($name === '') {
            throw new \InvalidArgumentException('Permission name cannot be empty.');
        }

        $this->name = $name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
