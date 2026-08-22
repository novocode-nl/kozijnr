<?php

namespace App\User\Infrastructure\Security;

use App\User\Domain\User;
use App\User\Domain\UserRepositoryInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Security user provider for the `super_admin` firewall. Backed directly
 * by UserRepositoryInterface rather than Doctrine's generic
 * EntityUserProvider, so this stays an explicit hexagonal adapter.
 *
 * Loads any public-schema User by email regardless of role — the actual
 * ROLE_SUPER_ADMIN requirement is enforced by security.yaml's
 * access_control against getRoles(), not here.
 */
final class UserProvider implements UserProviderInterface
{
    public function __construct(private readonly UserRepositoryInterface $repository)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->repository->findByEmail($identifier);

        if ($user === null) {
            throw new UserNotFoundException(sprintf('No user found for email "%s".', $identifier));
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_debug_type($user)));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === User::class || is_subclass_of($class, User::class);
    }
}
