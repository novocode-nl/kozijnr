<?php

namespace App\User\Domain;

/**
 * This context's own abstraction for hashing a plaintext password, so
 * Application never depends on Symfony directly.
 */
interface PasswordHasherInterface
{
    public function hash(User $user, string $plainPassword): string;
}
