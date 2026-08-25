<?php

namespace App\Tenancy\Application;

/**
 * Read-model for GetTenantLoginImage: the binary content plus just enough
 * metadata for the controller to build an HTTP response (Content-Type) —
 * mirrors App\ProfilePhoto\Application\ProfilePhotoContent.
 */
final class TenantLoginImageContent
{
    public function __construct(
        public readonly string $contents,
        public readonly string $mimeType,
    ) {
    }
}
