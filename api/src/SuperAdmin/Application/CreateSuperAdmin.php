<?php

namespace App\SuperAdmin\Application;

use App\SuperAdmin\Domain\Exception\SuperAdminAlreadyExistsException;
use App\SuperAdmin\Domain\SuperAdmin;
use App\SuperAdmin\Domain\SuperAdminRepositoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Creates a super-admin account (public schema only). Used by
 * `bin/console super-admin:create` — there is no self-service signup route,
 * on purpose: only an operator with console access can create the first
 * (or any further) super admin.
 */
final class CreateSuperAdmin
{
    public function __construct(
        private readonly SuperAdminRepositoryInterface $repository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function __invoke(string $email, string $plainPassword): SuperAdmin
    {
        $email = trim($email);

        if ($this->repository->findByEmail($email) !== null) {
            throw SuperAdminAlreadyExistsException::forEmail($email);
        }

        // Construct with a placeholder hash first: hashPassword() needs a
        // PasswordAuthenticatedUserInterface instance to hash against, and
        // SuperAdmin validates its password argument is non-empty.
        $superAdmin = new SuperAdmin($email, 'placeholder');
        $hashedPassword = $this->passwordHasher->hashPassword($superAdmin, $plainPassword);
        $superAdmin = new SuperAdmin($email, $hashedPassword);

        $this->repository->add($superAdmin);

        return $superAdmin;
    }
}
