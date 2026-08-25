<?php

namespace App\Tenancy\Application;

use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Storage\FileStorageInterface;
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
    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private const MAX_SIZE_IN_BYTES = 5 * 1024 * 1024; // 5 MiB

    private const EXTENSIONS_BY_MIME_TYPE = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly FileStorageInterface $storage,
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {
    }

    public function __invoke(Tenant $tenant, string $originalFilename, string $mimeType, string $contents): void
    {
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::create(
                sprintf('Unsupported login image mime type "%s".', $mimeType),
                'tenantSettings.error.unsupportedMimeType',
                ['mimeType' => $mimeType],
            );
        }

        $sizeInBytes = strlen($contents);

        if ($sizeInBytes === 0) {
            throw ValidationException::create(
                'Login image file is empty.',
                'tenantSettings.error.empty',
            );
        }

        if ($sizeInBytes > self::MAX_SIZE_IN_BYTES) {
            throw ValidationException::create(
                sprintf('Login image exceeds the maximum size of %d bytes.', self::MAX_SIZE_IN_BYTES),
                'tenantSettings.error.tooLarge',
                ['maxSizeInBytes' => self::MAX_SIZE_IN_BYTES],
            );
        }

        $existingKey = $tenant->getLoginImageStorageKey();
        if ($existingKey !== null) {
            $this->storage->delete($existingKey);
        }

        $storageKey = sprintf(
            'tenant-login-images/%d/%s.%s',
            $tenant->getId(),
            bin2hex(random_bytes(16)),
            self::EXTENSIONS_BY_MIME_TYPE[$mimeType],
        );

        $this->storage->write($storageKey, $contents);

        $tenant->setLoginImageStorageKey($storageKey);
        $this->tenantRepository->update($tenant);
    }
}
