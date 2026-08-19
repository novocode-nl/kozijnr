<?php

namespace App\SuperAdmin\Domain;

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
 * are stored in a `roles` column so future admin-side roles (besides
 * ROLE_SUPER_ADMIN) fit into this same table without a second entity/table.
 * Authorization against a specific role (e.g. `/api/admin/*` requiring
 * ROLE_SUPER_ADMIN) happens via Symfony's access_control against
 * getRoles(), not via a hardcoded role on this class.
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

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $roles;

    /**
     * @param list<string> $roles Must contain at least one role, e.g.
     *                            ['ROLE_SUPER_ADMIN'].
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
        $this->roles = array_values(array_unique($roles));
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
