<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * KOZ-32: `profile_photos` holds only metadata for an uploaded profile
 * photo (owner, mime-type, storage key, size, original filename) — the
 * binary content itself lives wherever the active FileStorageInterface
 * adapter puts it (local disk in dev, an S3-compatible bucket in
 * production), addressed only by `storage_key`.
 *
 * Public schema (alongside `users`): today's only owner is the
 * super-admin realm's App\User\Domain\User. One row per owner
 * (uniq_profile_photos_owner_id) — a new upload replaces the previous
 * photo rather than keeping history, see
 * App\ProfilePhoto\Application\UploadProfilePhoto.
 */
final class Version20260824130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the profile_photos table (KOZ-32 file storage: metadata only, never the binary content).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE profile_photos (
                id SERIAL NOT NULL,
                owner_id INT NOT NULL,
                storage_key VARCHAR(255) NOT NULL,
                mime_type VARCHAR(127) NOT NULL,
                size_in_bytes INT NOT NULL,
                original_filename VARCHAR(255) NOT NULL,
                uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_profile_photos_owner_id ON profile_photos (owner_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE profile_photos');
    }
}
