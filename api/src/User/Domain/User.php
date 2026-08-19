<?php

namespace App\User\Domain;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A generic public-schema user account, tenant-independent, that
 * authenticates only against the public schema. Rows of this entity live
 * exclusively in `public` — a tenant schema has no `users` table at all, so
 * an account here is structurally invisible from within a tenant schema,
 * not merely access-controlled away from it.
 *
 * Deliberately generic rather than a dedicated "SuperAdmin" entity: roles
 * are a many-to-many relation to the Role entity (KOZ-9), so future
 * admin-side roles (besides ROLE_SUPER_ADMIN) fit into this same table
 * without a second entity/table. Coarse authorization (e.g. the firewall's
 * access_control, or Symfony's built-in ROLE_ voter) still reads
 * getRoles() (role *names*), but fine-grained authorization (e.g.
 * #[IsGranted('tenant:list')]) reads hasPermission()/getPermissions(),
 * which resolve through each assigned Role's own Permission set rather
 * than comparing role-name strings.
 *
 * Deliberately its own Security `UserInterface` implementation, entirely
 * separate from any future tenant-user model: a super-admin session can only
 * ever resolve to a User here, never to a tenant user, and vice versa.
 */
#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_users_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180)]
    private string $email;

    /** Already-hashed password — never plaintext. */
    #[ORM\Column(type: 'string')]
    private string $password;

    /** @var Collection<int, Role> */
    #[ORM\ManyToMany(targetEntity: Role::class)]
    #[ORM\JoinTable(name: 'user_roles')]
    private Collection $assignedRoles;

    /**
     * @param list<Role> $roles Must contain at least one role, e.g.
     *                          [$roleSuperAdmin].
     */
    public function __construct(string $email, string $hashedPassword, array $roles)
    {
        $email = trim($email);

        if ($email === '') {
            throw new \InvalidArgumentException('User email cannot be empty.');
        }

        if ($hashedPassword === '') {
            throw new \InvalidArgumentException('User password hash cannot be empty.');
        }

        if ($roles === []) {
            throw new \InvalidArgumentException('User must have at least one role.');
        }

        $this->email = $email;
        $this->password = $hashedPassword;
        $this->assignedRoles = new ArrayCollection();

        foreach ($roles as $role) {
            if (!$this->assignedRoles->contains($role)) {
                $this->assignedRoles->add($role);
            }
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return array_values(array_unique(array_map(
            static fn (Role $role): string => $role->getName(),
            $this->assignedRoles->toArray(),
        )));
    }

    /**
     * True if any role assigned to this user grants the given permission.
     */
    public function hasPermission(string $permissionName): bool
    {
        foreach ($this->assignedRoles as $role) {
            if ($role->hasPermission($permissionName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * All permission names granted by this user's assigned roles, flattened
     * and deduplicated across roles.
     *
     * @return list<string>
     */
    public function getPermissions(): array
    {
        $permissions = [];

        foreach ($this->assignedRoles as $role) {
            foreach ($role->getPermissionNames() as $permissionName) {
                if (!in_array($permissionName, $permissions, true)) {
                    $permissions[] = $permissionName;
                }
            }
        }

        return $permissions;
    }

    public function eraseCredentials(): void
    {
        // No plaintext credential is ever held on this object, so there is
        // nothing to erase.
    }
}
