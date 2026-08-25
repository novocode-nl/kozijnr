<?php

declare(strict_types=1);

namespace App\Migrations\Tenant;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * KOZ-33: tenant-schema counterpart of the public-schema `profile_photos`
 * table created by Version20260824130000 (KOZ-32). That migration only
 * touches the public schema, whose only owner today is the super-admin
 * realm's App\User\Domain\User — tenant-user profile photos (owner_id =
 * App\TenantUser\Domain\TenantUser::getId()) need the identically-shaped
 * table inside every tenant schema instead, since App\ProfilePhoto\Domain\ProfilePhoto
 * is mapped once (attribute mapping, no per-schema Doctrine metadata) and
 * simply resolves against whatever schema is on search_path at query time.
 *
 * Same shape as the public-schema table on purpose: one row per owner
 * (uniq_profile_photos_owner_id), see App\ProfilePhoto\Application\UploadProfilePhoto
 * for the replace-on-reupload semantics.
 */
final class Version20260825090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the profile_photos table in the tenant schema (KOZ-33 tenant-user profile photos).';
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
