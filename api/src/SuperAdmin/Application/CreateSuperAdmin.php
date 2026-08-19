<?php

namespace App\SuperAdmin\Application;

use App\SuperAdmin\Domain\Exception\UserAlreadyExistsException;
use App\SuperAdmin\Domain\User;
use App\SuperAdmin\Domain\UserRepositoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Creates a super-admin account: a public-schema User row with the
 * ROLE_SUPER_ADMIN role. Used by `bin/console super-admin:create` — there is
 * no self-service signup route, on purpose: only an operator with console
 * access can create the first (or any further) super admin.
 */
final class CreateSuperAdmin
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
        private readonly UserPasswordHasherInterface $passwordHasher,
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
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user = new User($email, $hashedPassword, ['ROLE_SUPER_ADMIN']);

        $this->repository->add($user);

        return $user;
    }
}
