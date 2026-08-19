<?php

namespace App\User\Domain;

/**
 * This context's own abstraction for hashing a plaintext password, kept in
 * Domain alongside UserRepositoryInterface so Application never depends on
 * Symfony directly (rework, KOZ-8 round 6). Infrastructure provides the
 * real implementation by wrapping Symfony's
 * Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface.
 */
interface PasswordHasherInterface
{
    public function hash(User $user, string $plainPassword): string;
}
