<?php

namespace App\Shared\Domain\Storage;

use App\Shared\Domain\Exception\ValidationException;

/**
 * The upload rules ProfilePhoto (KOZ-32/33) and the tenant login image
 * (KOZ-34) deliberately share: same mime allowlist, same 5 MiB cap, same
 * random storage-key shape. The error keys and human-readable subject stay
 * caller-supplied because each feature reports its own keys
 * (profilePhoto.error.* vs tenantSettings.error.*) — the policy is shared,
 * the per-endpoint error contract is not. Callers pass FULL key literals
 * (via StoredImageErrorKeys), never a prefix + concatenation: the
 * check-contracts script only extracts complete `<domain>.error.<name>`
 * literals, so concatenated keys would silently fall out of that safety
 * net.
 */
final class StoredImagePolicy
{
    /** @var list<string> */
    public const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public const MAX_SIZE_IN_BYTES = 5 * 1024 * 1024; // 5 MiB

    /** @var array<string, string> */
    public const EXTENSIONS_BY_MIME_TYPE = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /** @var array<string, string> */
    public const MIME_TYPES_BY_EXTENSION = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    /**
     * @param string $subject Lowercase human phrase for the English message,
     *                        e.g. "profile photo" / "login image".
     */
    public static function assertValid(string $mimeType, string $contents, string $subject, StoredImageErrorKeys $errorKeys): void
    {
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::create(
                sprintf('Unsupported %s mime type "%s".', $subject, $mimeType),
                $errorKeys->unsupportedMimeType,
                ['mimeType' => $mimeType],
            );
        }

        $sizeInBytes = strlen($contents);

        if ($sizeInBytes === 0) {
            throw ValidationException::create(
                sprintf('%s file is empty.', ucfirst($subject)),
                $errorKeys->empty,
            );
        }

        if ($sizeInBytes > self::MAX_SIZE_IN_BYTES) {
            throw ValidationException::create(
                sprintf('%s exceeds the maximum size of %d bytes.', ucfirst($subject), self::MAX_SIZE_IN_BYTES),
                $errorKeys->tooLarge,
                ['maxSizeInBytes' => self::MAX_SIZE_IN_BYTES],
            );
        }
    }

    /** e.g. storageKey('profile-photos', 7, 'image/png') => "profile-photos/7/<32 hex>.png" */
    public static function storageKey(string $directory, int $ownerId, string $mimeType): string
    {
        return sprintf('%s/%d/%s.%s', $directory, $ownerId, bin2hex(random_bytes(16)), self::EXTENSIONS_BY_MIME_TYPE[$mimeType]);
    }

    public static function mimeTypeForStorageKey(string $storageKey): string
    {
        $extension = strtolower(pathinfo($storageKey, \PATHINFO_EXTENSION));

        return self::MIME_TYPES_BY_EXTENSION[$extension] ?? 'application/octet-stream';
    }
}
