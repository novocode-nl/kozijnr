<?php

namespace App\TenantUser\Application;

/**
 * Result of CreateTenantUserForTenant: the newly created tenant user plus
 * its generated plain-text password. Mirrors
 * App\Tenancy\Application\ProvisionedTenantWithAdmin — the plain-text
 * password is never persisted anywhere (only its hash is), so this is the
 * only place it ever exists outside that hash, and the controller must
 * hand it back to the caller in the same response.
 */
final class CreatedTenantUserForTenant
{
    public function __construct(
        public readonly string $email,
        /** @var list<string> */
        public readonly array $roles,
        public readonly string $password,
    ) {
    }

    /** @return array{email: string, roles: list<string>, password: string} */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'roles' => $this->roles,
            'password' => $this->password,
        ];
    }
}
