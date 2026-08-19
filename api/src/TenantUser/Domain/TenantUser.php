<?php

namespace App\TenantUser\Domain;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A tenant-scoped end-user account (KOZ-11) — the first tenant-side
 * authentication model in this codebase. Rows of this entity live
 * exclusively inside a tenant schema (see migrations-tenant/): the public
 * schema has no `tenant_users` table at all, so a tenant user is
 * structurally invisible outside its own tenant schema, exactly mirroring
 * how App\User\Domain\User (the super-admin account) is structurally
 * invisible from within a tenant schema. This is what makes cross-tenant
 * access structurally impossible rather than merely access-controlled
 * away, matching the KOZ-6/7 tenant-isolation approach.
 *
 * Deliberately its own bounded context and its own UserInterface
 * implementation, entirely separate from App\User\Domain\User: a tenant
 * user is its own domain concept (an end-user who happens to belong to one
 * tenant), not a "sub-type" of the super-admin User — see the KOZ-11 ticket
 * notes and the earlier KOZ-8 bounded-context-separation feedback this
 * follows.
 *
 * Roles are a plain string array here, not the Role/Permission entity
 * model App\User uses (KOZ-9): this is a deliberately minimal first version
 * (see the KOZ-11 ticket's "Out of scope"/"Kernpunten"). getRoles() is
 * enough to carry "the right role" onto the session/token and to work with
 * Symfony Security's built-in role checks; a fine-grained
 * permission-per-role model for tenant users, mirroring KOZ-9, can follow
 * as its own ticket if/when tenant-side authorization actually needs it —
 * the same evolution App\User went through from KOZ-8 to KOZ-9.
 */
#[ORM\Entity]
#[ORM\Table(name: 'tenant_users')]
#[ORM\UniqueConstraint(name: 'uniq_tenant_users_email', columns: ['email'])]
class TenantUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const DEFAULT_ROLE = 'ROLE_TENANT_USER';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180)]
    private string $email;

    /** Already-hashed password — never plaintext. */
    #[ORM\Column(type: 'string')]
    private string $password;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $roles;

    /**
     * @param list<string> $roles Defaults to [self::DEFAULT_ROLE] when empty.
     */
    public function __construct(string $email, string $hashedPassword, array $roles = [])
    {
        $email = trim($email);

        if ($email === '') {
            throw new \InvalidArgumentException('Tenant user email cannot be empty.');
        }

        if ($hashedPassword === '') {
            throw new \InvalidArgumentException('Tenant user password hash cannot be empty.');
        }

        $normalizedRoles = array_values(array_unique(array_map(
            static fn (string $role): string => trim($role),
            $roles,
        )));
        $normalizedRoles = array_values(array_filter($normalizedRoles, static fn (string $role): bool => $role !== ''));

        $this->email = $email;
        $this->password = $hashedPassword;
        $this->roles = $normalizedRoles === [] ? [self::DEFAULT_ROLE] : $normalizedRoles;
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
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
        // No plaintext credential is ever held on this object, so there is
        // nothing to erase.
    }
}
