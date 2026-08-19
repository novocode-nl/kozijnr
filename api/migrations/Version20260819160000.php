<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds `created_at` to `tenants` (public schema) — needed for KOZ-8's
 * super-admin tenant list ("subdomain + aanmaakdatum"). Existing rows get
 * `now()` as a one-off backfill default; new rows always set it explicitly
 * via App\Tenancy\Domain\Tenant's constructor.
 */
final class Version20260819160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add created_at to tenants (public schema).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenants ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NOW() NOT NULL');
        $this->addSql('ALTER TABLE tenants ALTER COLUMN created_at DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenants DROP created_at');
    }
}
