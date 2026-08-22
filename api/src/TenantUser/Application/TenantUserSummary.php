<?php

namespace App\TenantUser\Application;

use App\TenantUser\Domain\TenantUser;

/** Read-model DTO for the admin "users" tab on a tenant's detail page. */
final class TenantUserSummary
{
    public function __construct(
        public readonly string $email,
        /** @var list<string> */
        public readonly array $roles,
    ) {
    }

    public static function fromTenantUser(TenantUser $tenantUser): self
    {
        return new self($tenantUser->getEmail(), $tenantUser->getRoles());
    }

    /**
     * @return array{email: string, roles: list<string>}
     */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'roles' => $this->roles,
        ];
    }
}
