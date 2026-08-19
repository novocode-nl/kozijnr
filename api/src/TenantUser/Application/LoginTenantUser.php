<?php

namespace App\TenantUser\Application;

use App\TenantUser\Domain\Exception\InvalidCredentialsException;
use App\TenantUser\Domain\PasswordHasherInterface;
use App\TenantUser\Domain\TenantApiToken;
use App\TenantUser\Domain\TenantApiTokenRepositoryInterface;
use App\TenantUser\Domain\TenantUserRepositoryInterface;

/**
 * Validates an email+password combination against the tenant users living
 * in the *current* tenant schema (search_path already set by
 * App\Tenancy\Infrastructure\TenantResolverListener before this ever runs)
 * and, on success, issues a fresh TenantApiToken.
 *
 * Deliberately raises the exact same InvalidCredentialsException — same
 * class, same message — whether the email is unknown or the password is
 * wrong (KOZ-11 DoD): this is checked *here*, in Application, not left to
 * the controller to obscure after the fact, so there is no code path that
 * could leak the distinction some other way.
 */
final class LoginTenantUser
{
    public function __construct(
        private readonly TenantUserRepositoryInterface $userRepository,
        private readonly TenantApiTokenRepositoryInterface $tokenRepository,
        private readonly PasswordHasherInterface $passwordHasher,
    ) {
    }

    public function __invoke(string $email, string $plainPassword): string
    {
        $user = $this->userRepository->findByEmail(trim($email));

        if ($user === null || !$this->passwordHasher->verify($user, $plainPassword)) {
            throw InvalidCredentialsException::create();
        }

        $token = TenantApiToken::issueFor($user);
        $this->tokenRepository->add($token);

        return $token->getPlainTextToken();
    }
}
