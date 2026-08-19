<?php

namespace App\SuperAdmin\Infrastructure\Security;

use App\SuperAdmin\Domain\SuperAdmin;
use App\SuperAdmin\Domain\SuperAdminRepositoryInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Security user provider for the `super_admin` firewall only. Backed by
 * SuperAdminRepositoryInterface (the public-schema-only port) rather than
 * Doctrine's generic EntityUserProvider, so this stays an explicit
 * hexagonal adapter and never accidentally resolves any other user type.
 */
final class SuperAdminUserProvider implements UserProviderInterface
{
    public function __construct(private readonly SuperAdminRepositoryInterface $repository)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $superAdmin = $this->repository->findByEmail($identifier);

        if ($superAdmin === null) {
            throw new UserNotFoundException(sprintf('No super admin found for email "%s".', $identifier));
        }

        return $superAdmin;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SuperAdmin) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_debug_type($user)));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === SuperAdmin::class || is_subclass_of($class, SuperAdmin::class);
    }
}
