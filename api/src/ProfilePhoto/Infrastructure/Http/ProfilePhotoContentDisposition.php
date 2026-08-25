<?php

namespace App\ProfilePhoto\Infrastructure\Http;

use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Builds the `Content-Disposition` header for serving a stored profile
 * photo back to the client. Shared by every "get my profile photo"
 * controller (admin and tenant-user alike) so the KOZ-32 non-ASCII-filename
 * fix lives in exactly one place instead of being copy-pasted per realm.
 *
 * KOZ-32 rework: HeaderUtils::makeDisposition() requires its ASCII
 * "filename" fallback to contain only printable ASCII and no '%', and
 * requires BOTH the real filename and the fallback to be free of '/'
 * and '\'. originalFilename is the unsanitized, client-supplied name
 * (only path separators are stripped by Symfony's UploadedFile::getName(),
 * non-ASCII characters and '%' are left intact), so passing it straight
 * through — with no third argument, which makes Symfony fall back to
 * the filename itself as the ASCII fallback — throws an uncaught
 * InvalidArgumentException for names like "café.png", "evil/name.png"
 * or "photo%20mine.png", turning every subsequent GET into a 500.
 *
 * We defensively strip path separators from the real filename (belt and
 * braces on top of UploadedFile's own stripping) and derive a safe,
 * ASCII-only, '%'-free fallback from it. The real (possibly non-ASCII)
 * filename is still preserved via the RFC 6266 filename* parameter that
 * Symfony adds automatically whenever filename !== filenameFallback.
 */
final class ProfilePhotoContentDisposition
{
    public static function forOriginalFilename(string $originalFilename): string
    {
        $safeFilename = str_replace(['/', '\\'], '_', $originalFilename);

        $asciiFallback = preg_replace('/[^\x20-\x7e]|%/', '_', $safeFilename);
        if ($asciiFallback === '' || trim($asciiFallback, '_') === '') {
            $extension = preg_replace('/[^\x20-\x7e]|%/', '', pathinfo($safeFilename, PATHINFO_EXTENSION));
            $asciiFallback = 'profile-photo' . ($extension !== '' ? '.' . $extension : '');
        }

        return HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            $safeFilename,
            $asciiFallback,
        );
    }
}
