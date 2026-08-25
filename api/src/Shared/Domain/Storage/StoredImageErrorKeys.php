<?php

namespace App\Shared\Domain\Storage;

/**
 * The three error keys an image-accepting feature reports for the shared
 * StoredImagePolicy checks — full literals per call site (see the policy's
 * docblock for why these are never built by concatenation).
 */
final class StoredImageErrorKeys
{
    public function __construct(
        public readonly string $unsupportedMimeType,
        public readonly string $empty,
        public readonly string $tooLarge,
    ) {
    }
}
