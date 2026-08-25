<?php

namespace App\Shared\Domain\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * Shared abstraction for hashing/verifying passwords, so Application code
 * never depends on Symfony's hasher directly. One port for both realms:
 * the previous per-context ports (User\Domain, TenantUser\Domain) were the
 * same pattern twice, and Symfony's own UserPasswordHasherInterface is
 * already generic over PasswordAuthenticatedUserInterface — which both
 * User and TenantUser implement. Domain already depends on that Symfony
 * interface via the entities themselves, so this introduces no new
 * layering direction.
 */
interface PasswordHasherInterface
{
    public function hash(PasswordAuthenticatedUserInterface $user, string $plainPassword): string;

    public function verify(PasswordAuthenticatedUserInterface $user, string $plainPassword): bool;
}
