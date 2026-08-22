<?php

namespace App\User\Domain;

interface UserRepositoryInterface
{
    /** Looks up a user by email. Must always query the public schema — the only schema `users` exists in. */
    public function findByEmail(string $email): ?User;

    public function add(User $user): void;
}
