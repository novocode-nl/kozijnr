<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * KOZ-34: tenant-level settings a tenant-admin can manage — the login
 * screen's default language and its login-screen image — live on the
 * `tenants` table (public schema), not inside the tenant schema.
 *
 * Both must be readable purely from the subdomain, before any tenant-schema
 * context or authenticated session exists (see
 * App\Tenancy\Infrastructure\TenantResolverListener, which resolves the
 * `Tenant` row from `public.tenants` before it ever switches search_path),
 * so the public schema is the only layer that fits.
 *
 * `login_image_storage_key` mirrors `profile_photos.storage_key`
 * (KOZ-32): an opaque key into whichever FileStorageInterface adapter is
 * active, never the binary content itself and never a raw filesystem path.
 */
final class Version20260825093300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add default_locale and login_image_storage_key to tenants (KOZ-34 tenant settings).';
    }

    public function up(Schema $schema): void
    {
        // The DB-level default is kept (not dropped after backfilling
        // existing rows, unlike a typical add-NOT-NULL-column migration):
        // several existing tests insert directly into `tenants` via raw SQL
        // without listing every column, and a stray manual INSERT should
        // sensibly default to 'nl' rather than fail outright. The
        // application layer (Tenant's constructor) always sets this
        // explicitly regardless.
        $this->addSql("ALTER TABLE tenants ADD default_locale VARCHAR(5) NOT NULL DEFAULT 'nl'");
        $this->addSql('ALTER TABLE tenants ADD login_image_storage_key VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenants DROP login_image_storage_key');
        $this->addSql('ALTER TABLE tenants DROP default_locale');
    }
}
