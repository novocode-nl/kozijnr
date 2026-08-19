<?php

namespace App\SuperAdmin\Domain;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A super-admin account, tenant-independent, that authenticates only against
 * the public schema. Rows of this entity live exclusively in `public` — a
 * tenant schema has no `super_admins` table at all, so a super admin is
 * structurally invisible from within a tenant schema, not merely
 * access-controlled away from it.
 *
 * Deliberately its own Security `UserInterface` implementation, entirely
 * separate from any future tenant-user model: a super-admin session can only
 * ever resolve to a SuperAdmin, never to a tenant user, and vice versa.
 */
#[ORM\Entity]
#[ORM\Table(name: 'super_admins')]
#[ORM\UniqueConstraint(name: 'uniq_super_admins_email', columns: ['email'])]
class SuperAdmin implements UserInterface, PasswordAuthenticatedUserInterface
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

    public function __construct(string $email, string $hashedPassword)
    {
        $email = trim($email);

        if ($email === '') {
            throw new \InvalidArgumentException('Super-admin email cannot be empty.');
        }

        if ($hashedPassword === '') {
            throw new \InvalidArgumentException('Super-admin password hash cannot be empty.');
        }

        $this->email = $email;
        $this->password = $hashedPassword;
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
     * Every super admin carries exactly this one role — there is no
     * hierarchy or per-tenant permission model in scope for this ticket
     * (see KOZ-8 "Out of scope").
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        return ['ROLE_SUPER_ADMIN'];
    }

    public function eraseCredentials(): void
    {
        // No plaintext credential is ever held on this object, so there is
        // nothing to erase.
    }
}
