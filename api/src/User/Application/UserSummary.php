<?php

namespace App\User\Application;

use App\User\Domain\User;

/**
 * Read-model DTO for the admin user overview (KOZ-30) — mirrors
 * App\Tenancy\Application\TenantSummary's role in the tenant overview.
 */
final class UserSummary
{
    /** @param list<string> $roles */
    public function __construct(
        public readonly string $email,
        public readonly array $roles,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self($user->getEmail(), $user->getRoles());
    }

    /** @return array{email: string, roles: list<string>} */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'roles' => $this->roles,
        ];
    }
}
