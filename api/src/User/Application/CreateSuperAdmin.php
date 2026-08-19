<?php

namespace App\User\Application;

use App\User\Domain\Exception\UserAlreadyExistsException;
use App\User\Domain\PasswordHasherInterface;
use App\User\Domain\User;
use App\User\Domain\UserRepositoryInterface;

/**
 * Creates a super-admin account: a plain public-schema User (this bounded
 * context's own model) with the ROLE_SUPER_ADMIN role. This use case lives
 * in App\User, not in a separate "SuperAdmin" context (rework, KOZ-8):
 * "super admin" is an authorization concern (a role on a User), not a
 * domain concept of its own — creating an admin account is User-context
 * business, same as any other User creation would be. Used by
 * `bin/console super-admin:create` — there is no self-service signup route,
 * on purpose: only an operator with console access can create the first (or
 * any further) super admin.
 */
final class CreateSuperAdmin
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
        private readonly PasswordHasherInterface $passwordHasher,
    ) {
    }

    public function __invoke(string $email, string $plainPassword): User
    {
        $email = trim($email);

        if ($this->repository->findByEmail($email) !== null) {
            throw UserAlreadyExistsException::forEmail($email);
        }

        // Construct with a placeholder hash first: hashPassword() needs a
        // PasswordAuthenticatedUserInterface instance to hash against, and
        // User validates its password argument is non-empty.
        $user = new User($email, 'placeholder', ['ROLE_SUPER_ADMIN']);
        $hashedPassword = $this->passwordHasher->hash($user, $plainPassword);
        $user = new User($email, $hashedPassword, ['ROLE_SUPER_ADMIN']);

        $this->repository->add($user);

        return $user;
    }
}
