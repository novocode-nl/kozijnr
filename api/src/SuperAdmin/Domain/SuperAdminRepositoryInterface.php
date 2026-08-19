<?php

namespace App\SuperAdmin\Domain;

interface SuperAdminRepositoryInterface
{
    /**
     * Looks up a super admin by email. Must always query the public schema
     * — the only schema `super_admins` exists in.
     */
    public function findByEmail(string $email): ?SuperAdmin;

    /**
     * Registers a newly created super admin in the public `super_admins`
     * table.
     */
    public function add(SuperAdmin $superAdmin): void;
}
