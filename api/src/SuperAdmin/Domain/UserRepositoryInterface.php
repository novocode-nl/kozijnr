<?php

namespace App\SuperAdmin\Domain;

interface UserRepositoryInterface
{
    /**
     * Looks up a user by email. Must always query the public schema — the
     * only schema `users` exists in.
     */
    public function findByEmail(string $email): ?User;

    /**
     * Registers a newly created user in the public `users` table.
     */
    public function add(User $user): void;
}
