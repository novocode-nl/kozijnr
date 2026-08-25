<?php

namespace App\Tenancy\Application;

use App\Shared\Domain\Storage\FileStorageInterface;
use App\Tenancy\Domain\Tenant;

/**
 * KOZ-34: reads back the login-screen image for a tenant (already resolved
 * from the subdomain by TenantResolverListener — see
 * GetTenantLoginImageController), so the frontend's login screen can show
 * it without any authenticated session at all.
 */
final class GetTenantLoginImage
{
    /** @var array<string, string> */
    private const MIME_TYPES_BY_EXTENSION = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    public function __construct(private readonly FileStorageInterface $storage)
    {
    }

    public function __invoke(Tenant $tenant): ?TenantLoginImageContent
    {
        $storageKey = $tenant->getLoginImageStorageKey();
        if ($storageKey === null) {
            return null;
        }

        $extension = strtolower(pathinfo($storageKey, PATHINFO_EXTENSION));
        $mimeType = self::MIME_TYPES_BY_EXTENSION[$extension] ?? 'application/octet-stream';

        return new TenantLoginImageContent($this->storage->read($storageKey), $mimeType);
    }
}
