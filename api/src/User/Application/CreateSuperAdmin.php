<?php

namespace App\User\Application;

use App\User\Domain\Exception\RoleNotFoundException;
use App\User\Domain\Exception\UserAlreadyExistsException;
use App\Shared\Domain\Security\PasswordHasherInterface;
use App\User\Domain\RoleRepositoryInterface;
use App\User\Domain\User;
use App\User\Domain\UserRepositoryInterface;

/**
 * Creates a super-admin account: a plain public-schema User assigned the
 * ROLE_SUPER_ADMIN role. Used by `bin/console super-admin:create` — no
 * self-service signup route exists, on purpose.
 *
 * ROLE_SUPER_ADMIN itself is looked up rather than constructed here:
 * roles/permissions are seeded via migration.
 */
final class CreateSuperAdmin
{
    private const SUPER_ADMIN_ROLE_NAME = 'ROLE_SUPER_ADMIN';

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly RoleRepositoryInterface $roleRepository,
        private readonly PasswordHasherInterface $passwordHasher,
    ) {
    }

    public function __invoke(string $email, string $plainPassword): User
    {
        $email = trim($email);

        if ($this->userRepository->findByEmail($email) !== null) {
            throw UserAlreadyExistsException::forEmail($email);
        }

        $superAdminRole = $this->roleRepository->findByName(self::SUPER_ADMIN_ROLE_NAME);

        if ($superAdminRole === null) {
            throw RoleNotFoundException::forName(self::SUPER_ADMIN_ROLE_NAME);
        }

        // Construct with a placeholder hash first: hashPassword() needs a
        // PasswordAuthenticatedUserInterface instance to hash against, and
        // User validates its password argument is non-empty.
        $user = new User($email, 'placeholder', [$superAdminRole]);
        $hashedPassword = $this->passwordHasher->hash($user, $plainPassword);
        $user = new User($email, $hashedPassword, [$superAdminRole]);

        $this->userRepository->add($user);

        return $user;
    }
}
