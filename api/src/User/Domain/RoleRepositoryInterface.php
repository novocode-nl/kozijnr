<?php

namespace App\User\Domain;

interface RoleRepositoryInterface
{
    /**
     * Looks up a role by its unique name (e.g. `ROLE_SUPER_ADMIN`). Roles
     * are seeded via migration (KOZ-9), not created through this
     * repository — there is deliberately no `add()` method here yet, as
     * role/permission management is out of scope for this ticket.
     */
    public function findByName(string $name): ?Role;
}
