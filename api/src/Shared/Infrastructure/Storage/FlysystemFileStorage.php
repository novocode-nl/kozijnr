<?php

namespace App\Shared\Infrastructure\Storage;

use App\Shared\Domain\Storage\Exception\FileNotFoundException;
use App\Shared\Domain\Storage\FileStorageInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;

/**
 * FileStorageInterface implementation backed by a League\Flysystem
 * FilesystemOperator. Which concrete adapter that operator wraps (local
 * disk vs an S3-compatible bucket) is decided entirely by
 * FlysystemAdapterFactory / services.yaml config — this class never knows
 * or cares, which is the point of the port/adapter split (KOZ-32).
 */
final class FlysystemFileStorage implements FileStorageInterface
{
    public function __construct(
        private readonly FilesystemOperator $filesystem,
    ) {
    }

    public function write(string $key, string $contents): void
    {
        try {
            $this->filesystem->write($key, $contents);
        } catch (UnableToWriteFile|FilesystemException $exception) {
            throw new \RuntimeException(sprintf('Failed to write file for storage key "%s".', $key), previous: $exception);
        }
    }

    public function read(string $key): string
    {
        try {
            return $this->filesystem->read($key);
        } catch (UnableToReadFile) {
            throw FileNotFoundException::forKey($key);
        } catch (FilesystemException $exception) {
            throw new \RuntimeException(sprintf('Failed to read file for storage key "%s".', $key), previous: $exception);
        }
    }

    public function delete(string $key): void
    {
        try {
            $this->filesystem->delete($key);
        } catch (UnableToDeleteFile|FilesystemException $exception) {
            throw new \RuntimeException(sprintf('Failed to delete file for storage key "%s".', $key), previous: $exception);
        }
    }

    public function exists(string $key): bool
    {
        try {
            return $this->filesystem->fileExists($key);
        } catch (FilesystemException $exception) {
            throw new \RuntimeException(sprintf('Failed to check existence for storage key "%s".', $key), previous: $exception);
        }
    }
}
