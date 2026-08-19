<?php

namespace App\TenantUser\Application;

use App\TenantUser\Domain\Exception\TenantUserAlreadyExistsException;
use App\TenantUser\Domain\PasswordHasherInterface;
use App\TenantUser\Domain\TenantUser;
use App\TenantUser\Domain\TenantUserRepositoryInterface;

/**
 * Creates a tenant user account within the *current* tenant schema
 * (search_path already pointed at the right tenant by the caller — see
 * App\TenantUser\Infrastructure\Command\CreateTenantUserCommand). There is
 * no self-service registration route on purpose (out of scope for KOZ-11,
 * same reasoning as CreateSuperAdmin): only an operator with console access
 * can create a tenant user account today.
 */
final class CreateTenantUser
{
    public function __construct(
        private readonly TenantUserRepositoryInterface $userRepository,
        private readonly PasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * @param list<string> $roles
     */
    public function __invoke(string $email, string $plainPassword, array $roles = []): TenantUser
    {
        $email = trim($email);

        if ($this->userRepository->findByEmail($email) !== null) {
            throw TenantUserAlreadyExistsException::forEmail($email);
        }

        // Construct with a placeholder hash first: hash() needs a
        // PasswordAuthenticatedUserInterface instance to hash against, and
        // TenantUser validates its password argument is non-empty.
        $user = new TenantUser($email, 'placeholder', $roles);
        $hashedPassword = $this->passwordHasher->hash($user, $plainPassword);
        $user = new TenantUser($email, $hashedPassword, $roles);

        $this->userRepository->add($user);

        return $user;
    }
}
