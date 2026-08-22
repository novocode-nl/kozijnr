<?php

namespace App\User\Domain;

interface RoleRepositoryInterface
{
    /**
     * Looks up a role by its unique name (e.g. `ROLE_SUPER_ADMIN`). Roles
     * are seeded via migration, not created through this repository —
     * there is deliberately no `add()` method here yet.
     */
    public function findByName(string $name): ?Role;
}
