<?php

namespace App\Shared\Domain\Storage\Exception;

/**
 * Raised by a FileStorageInterface implementation when read() is called
 * for a storage key that does not exist.
 */
final class FileNotFoundException extends \RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self(sprintf('No file exists for storage key "%s".', $key));
    }
}
