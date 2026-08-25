<?php

namespace App\ProfilePhoto\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Metadata for one uploaded profile photo — id, mime-type, storage key and
 * owner only. The binary file content itself is never held here or in any
 * other entity (KOZ-32 DoD): it lives wherever FileStorageInterface's
 * active adapter puts it, addressed only by `storageKey`, an opaque
 * key/path chosen at upload time — never a raw filesystem path.
 *
 * One row per owner: App\ProfilePhoto\Application\UploadProfilePhoto
 * replaces (deletes the old stored file + row) rather than keeping a
 * history of past photos — see that class for why.
 */
#[ORM\Entity]
#[ORM\Table(name: 'profile_photos')]
#[ORM\UniqueConstraint(name: 'uniq_profile_photos_owner_id', columns: ['owner_id'])]
class ProfilePhoto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'owner_id', type: 'integer')]
    private int $ownerId;

    #[ORM\Column(name: 'storage_key', type: 'string', length: 255)]
    private string $storageKey;

    #[ORM\Column(name: 'mime_type', type: 'string', length: 127)]
    private string $mimeType;

    #[ORM\Column(name: 'size_in_bytes', type: 'integer')]
    private int $sizeInBytes;

    #[ORM\Column(name: 'original_filename', type: 'string', length: 255)]
    private string $originalFilename;

    #[ORM\Column(name: 'uploaded_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $uploadedAt;

    private function __construct(
        int $ownerId,
        string $storageKey,
        string $mimeType,
        int $sizeInBytes,
        string $originalFilename,
        \DateTimeImmutable $uploadedAt,
    ) {
        if ($ownerId <= 0) {
            throw new \InvalidArgumentException('ProfilePhoto ownerId must be a positive integer.');
        }

        if (trim($storageKey) === '') {
            throw new \InvalidArgumentException('ProfilePhoto storageKey cannot be empty.');
        }

        if (trim($mimeType) === '') {
            throw new \InvalidArgumentException('ProfilePhoto mimeType cannot be empty.');
        }

        if ($sizeInBytes <= 0) {
            throw new \InvalidArgumentException('ProfilePhoto sizeInBytes must be a positive integer.');
        }

        if (trim($originalFilename) === '') {
            throw new \InvalidArgumentException('ProfilePhoto originalFilename cannot be empty.');
        }

        $this->ownerId = $ownerId;
        $this->storageKey = $storageKey;
        $this->mimeType = $mimeType;
        $this->sizeInBytes = $sizeInBytes;
        $this->originalFilename = $originalFilename;
        $this->uploadedAt = $uploadedAt;
    }

    public static function uploadedFor(
        int $ownerId,
        string $storageKey,
        string $mimeType,
        int $sizeInBytes,
        string $originalFilename,
    ): self {
        return new self($ownerId, $storageKey, $mimeType, $sizeInBytes, $originalFilename, new \DateTimeImmutable());
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    public function getStorageKey(): string
    {
        return $this->storageKey;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSizeInBytes(): int
    {
        return $this->sizeInBytes;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }
}
