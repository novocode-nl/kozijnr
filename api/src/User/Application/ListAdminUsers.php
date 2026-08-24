<?php

namespace App\User\Application;

use App\User\Domain\UserRepositoryInterface;

/**
 * Read-only query for the admin user overview (KOZ-30), analogous to
 * App\Tenancy\Application\ListTenants. Every row in `public.users` is an
 * admin account by construction (see App\User\Domain\User's doc comment),
 * so this simply lists all of them — no tenant-scoping applies.
 */
final class ListAdminUsers
{
    public function __construct(private readonly UserRepositoryInterface $userRepository)
    {
    }

    /** @return UserSummary[] */
    public function __invoke(): array
    {
        return array_map(UserSummary::fromUser(...), $this->userRepository->findAll());
    }
}
