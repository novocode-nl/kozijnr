<?php

namespace App\Tenancy\Application;

use App\Shared\Domain\Storage\FileStorageInterface;
use App\Shared\Domain\Storage\StoredImageErrorKeys;
use App\Shared\Domain\Storage\StoredImagePolicy;
use App\Tenancy\Domain\Tenant;
use App\Tenancy\Domain\TenantRepositoryInterface;

/**
 * KOZ-34: command handler that validates and stores a tenant's
 * login-screen image via the FileStorageInterface port (KOZ-32), then
 * records the resulting storage key on the `Tenant` itself.
 *
 * Deliberately mirrors App\ProfilePhoto\Application\UploadProfilePhoto:
 * same mime-type allowlist, same 5MB limit, same "replace, don't version"
 * behaviour (an existing login image is fully replaced — deleted from
 * storage — rather than kept). Takes the already-resolved `Tenant`, never a
 * client-supplied subdomain, same reasoning as UpdateTenantDefaultLocale.
 */
final class UploadTenantLoginImage
{
    public function __construct(
        private readonly FileStorageInterface $storage,
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {
    }

    public function __invoke(Tenant $tenant, string $originalFilename, string $mimeType, string $contents): void
    {
        StoredImagePolicy::assertValid($mimeType, $contents, 'login image', new StoredImageErrorKeys(
            'tenantSettings.error.unsupportedMimeType',
            'tenantSettings.error.empty',
            'tenantSettings.error.tooLarge',
        ));

        $existingKey = $tenant->getLoginImageStorageKey();
        if ($existingKey !== null) {
            $this->storage->delete($existingKey);
        }

        $storageKey = StoredImagePolicy::storageKey('tenant-login-images', $tenant->getId(), $mimeType);

        $this->storage->write($storageKey, $contents);

        $tenant->setLoginImageStorageKey($storageKey);
        $this->tenantRepository->update($tenant);
    }
}
