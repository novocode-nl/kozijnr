<?php

namespace App\Shared\Domain\Storage;

use App\Shared\Domain\Storage\Exception\FileNotFoundException;

/**
 * Port for storing/retrieving arbitrary binary files (e.g. profile photos)
 * by an opaque storage key — no Domain or Application code is ever aware
 * of *how* or *where* a file physically lives (local disk, S3-compatible
 * bucket, ...). Infrastructure/Storage/FlysystemFileStorage is the only
 * implementation, backed by League\Flysystem, whose concrete adapter
 * (local vs S3) is chosen purely by config — see
 * App\Shared\Infrastructure\Storage\FlysystemAdapterFactory.
 *
 * Deliberately dependency-free (no Flysystem types leak through this
 * interface) so Domain/Application code compiles and tests without ever
 * requiring league/flysystem.
 *
 * The storage key is an opaque path-like string (e.g.
 * "profile-photos/42/ab12cd34.jpg") chosen by the caller — never a raw
 * filesystem path, and never persisted anywhere except as metadata
 * alongside the owning entity (see App\ProfilePhoto\Domain\ProfilePhoto).
 */
interface FileStorageInterface
{
    public function write(string $key, string $contents): void;

    /** @throws FileNotFoundException when no file exists for $key */
    public function read(string $key): string;

    public function delete(string $key): void;

    public function exists(string $key): bool;
}
