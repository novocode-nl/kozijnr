<?php

namespace App\Tenancy\Application;

use App\Tenancy\Domain\Tenant;

/**
 * KOZ-34: read-model DTO for the tenant settings page (GET /api/settings).
 * `hasLoginImage` rather than the storage key itself — the key is an
 * internal detail (same reasoning as ProfilePhoto/tenant admin DTOs never
 * exposing it); the frontend only needs to know whether to render a
 * preview, and fetches the actual bytes from GET /api/login-image.
 */
final class TenantSettings
{
    public function __construct(
        public readonly string $defaultLocale,
        public readonly bool $hasLoginImage,
    ) {
    }

    public static function fromTenant(Tenant $tenant): self
    {
        return new self($tenant->getDefaultLocale(), $tenant->getLoginImageStorageKey() !== null);
    }

    /** @return array{defaultLocale: string, hasLoginImage: bool} */
    public function toArray(): array
    {
        return [
            'defaultLocale' => $this->defaultLocale,
            'hasLoginImage' => $this->hasLoginImage,
        ];
    }
}
