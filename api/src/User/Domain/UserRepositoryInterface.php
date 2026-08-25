<?php

namespace App\User\Domain;

interface UserRepositoryInterface
{
    /** Looks up a user by email. Must always query the public schema — the only schema `users` exists in. */
    public function findByEmail(string $email): ?User;

    public function add(User $user): void;

    /**
     * All admin users, ordered by email — backs the admin user overview
     * (KOZ-30). Every row is an admin account by construction (see User's
     * doc comment), so this needs no further filtering.
     *
     * @return User[]
     */
    public function findAll(): array;
}
