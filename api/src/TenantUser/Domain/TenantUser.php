<?php

namespace App\TenantUser\Domain;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A tenant-scoped end-user account. Rows live exclusively inside a tenant
 * schema — the public schema has no `tenant_users` table at all, so a
 * tenant user is structurally invisible outside its own tenant schema,
 * mirroring how App\User\Domain\User (the super-admin account) is
 * structurally invisible from within a tenant schema. This makes
 * cross-tenant access structurally impossible rather than merely
 * access-controlled away.
 *
 * Deliberately its own bounded context and UserInterface implementation,
 * entirely separate from App\User\Domain\User: a tenant user is its own
 * domain concept, not a "sub-type" of the super-admin User.
 *
 * Roles are a plain string array here, not the Role/Permission entity
 * model App\User uses — a deliberately minimal first version. A
 * fine-grained permission-per-role model can follow if/when tenant-side
 * authorization actually needs it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'tenant_users')]
#[ORM\UniqueConstraint(name: 'uniq_tenant_users_email', columns: ['email'])]
class TenantUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const DEFAULT_ROLE = 'ROLE_TENANT_USER';

    /** Automatically assigned to the account CreateTenantController creates for a freshly provisioned tenant. */
    public const ROLE_TENANT_ADMIN = 'ROLE_TENANT_ADMIN';

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
