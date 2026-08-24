<?php

namespace App\ProfilePhoto\Application;

/**
 * Read-model for GetProfilePhoto: the binary content plus just enough
 * metadata for the controller to build an HTTP response (Content-Type,
 * filename) — never handed back as, or stored on, a Domain entity.
 */
final class ProfilePhotoContent
{
    public function __construct(
        public readonly string $contents,
        public readonly string $mimeType,
        public readonly string $originalFilename,
    ) {
    }
}
