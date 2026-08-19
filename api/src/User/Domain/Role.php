<?php

namespace App\User\Domain;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A named role (e.g. `ROLE_SUPER_ADMIN`) that grants a set of Permissions.
 * Its own database table (KOZ-9), replacing the plain `roles` string array
 * that used to live directly on User: a role's permissions are data, not
 * something hardcoded in an authorization check, so new permissions can be
 * granted to an existing role via seed data instead of a code change.
 *
 * The role *name* itself is still used as the Symfony Security role string
 * (getRoles() on User maps to these names) so existing role-based
 * mechanisms (e.g. the firewall's access_control) keep working unchanged;
 * only fine-grained authorization (e.g. #[IsGranted('tenant:list')]) goes
 * through the permission relation on this class instead.
 */
#[ORM\Entity]
#[ORM\Table(name: 'roles')]
#[ORM\UniqueConstraint(name: 'uniq_roles_name', columns: ['name'])]
class Role
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    /** @var Collection<int, Permission> */
    #[ORM\ManyToMany(targetEntity: Permission::class)]
    #[ORM\JoinTable(name: 'role_permissions')]
    private Collection $permissions;

    public function __construct(string $name)
    {
        $name = trim($name);

        if ($name === '') {
            throw new \InvalidArgumentException('Role name cannot be empty.');
        }

        $this->name = $name;
        $this->permissions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function addPermission(Permission $permission): void
    {
        if ($this->permissions->contains($permission)) {
            return;
        }

        $this->permissions->add($permission);
    }

    public function hasPermission(string $permissionName): bool
    {
        foreach ($this->permissions as $permission) {
            if ($permission->getName() === $permissionName) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function getPermissionNames(): array
    {
        return array_values(array_map(
            static fn (Permission $permission): string => $permission->getName(),
            $this->permissions->toArray(),
        ));
    }
}
