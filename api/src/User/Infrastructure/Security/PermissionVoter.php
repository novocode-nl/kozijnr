<?php

namespace App\User\Infrastructure\Security;

use App\User\Domain\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Authorizes fine-grained permission attributes (e.g. `tenant:list`,
 * `tenant:create`) against the currently authenticated User's assigned
 * roles (KOZ-9), replacing role-name-string comparisons
 * (#[IsGranted('ROLE_SUPER_ADMIN')]) for anything more specific than
 * "is this a super admin at all".
 *
 * Distinguishes a permission attribute from Symfony's built-in ones (role
 * names, IS_AUTHENTICATED_FULLY, PUBLIC_ACCESS, ...) by convention: this
 * bounded context's permission names always contain a `:` (e.g.
 * `tenant:list`), which none of Symfony's reserved attributes do. Any
 * other attribute is left to the other registered voters (abstain).
 */
final class PermissionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_contains($attribute, ':');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return $user->hasPermission($attribute);
    }
}
