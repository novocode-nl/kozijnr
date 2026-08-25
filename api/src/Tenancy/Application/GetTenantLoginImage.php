<?php

namespace App\Tenancy\Application;

use App\Shared\Domain\Storage\FileStorageInterface;
use App\Shared\Domain\Storage\StoredImagePolicy;
use App\Tenancy\Domain\Tenant;

/**
 * KOZ-34: reads back the login-screen image for a tenant (already resolved
 * from the subdomain by TenantResolverListener — see
 * GetTenantLoginImageController), so the frontend's login screen can show
 * it without any authenticated session at all.
 */
final class GetTenantLoginImage
{
    public function __construct(private readonly FileStorageInterface $storage)
    {
    }

    public function __invoke(Tenant $tenant): ?TenantLoginImageContent
    {
        $storageKey = $tenant->getLoginImageStorageKey();
        if ($storageKey === null) {
            return null;
        }

        $mimeType = StoredImagePolicy::mimeTypeForStorageKey($storageKey);

        return new TenantLoginImageContent($this->storage->read($storageKey), $mimeType);
    }
}
