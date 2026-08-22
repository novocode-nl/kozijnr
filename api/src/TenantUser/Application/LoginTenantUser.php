<?php

namespace App\TenantUser\Application;

use App\TenantUser\Domain\Exception\InvalidCredentialsException;
use App\TenantUser\Domain\PasswordHasherInterface;
use App\TenantUser\Domain\TenantApiToken;
use App\TenantUser\Domain\TenantApiTokenRepositoryInterface;
use App\TenantUser\Domain\TenantUserRepositoryInterface;

/**
 * Validates an email+password combination against the tenant users in the
 * *current* tenant schema and, on success, issues a fresh TenantApiToken.
 *
 * Deliberately raises the exact same InvalidCredentialsException — same
 * class, same message — whether the email is unknown or the password is
 * wrong, checked here rather than left to the controller to obscure after
 * the fact, so no code path can leak the distinction.
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
