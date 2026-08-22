<?php

namespace App\User\Infrastructure\Security;

use App\User\Domain\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Authorizes fine-grained permission attributes (e.g. `tenant:list`)
 * against the currently authenticated User's assigned roles, replacing
 * role-name-string comparisons for anything more specific than "is this a
 * super admin at all".
 *
 * Distinguishes a permission attribute from Symfony's built-in ones by
 * convention: this context's permission names always contain a `:`, which
 * none of Symfony's reserved attributes do.
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
