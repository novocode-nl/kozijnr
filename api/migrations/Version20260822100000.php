<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds `name` (free-text display name, distinct from the subdomain slug)
 * and `archived_at` (soft-delete marker, KOZ-27) to `tenants` (public
 * schema).
 *
 * Existing rows get their subdomain backfilled as their name — a
 * reasonable default for any tenant provisioned before this ticket, which
 * only ever had a single subdomain-doubling-as-name value anyway. New rows
 * always set `name` explicitly via App\Tenancy\Domain\Tenant's
 * constructor.
 */
final class Version20260822100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add name and archived_at to tenants (public schema).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenants ADD name VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE tenants SET name = subdomain WHERE name IS NULL');
        $this->addSql('ALTER TABLE tenants ALTER COLUMN name SET NOT NULL');

        $this->addSql('ALTER TABLE tenants ADD archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenants DROP archived_at');
        $this->addSql('ALTER TABLE tenants DROP name');
    }
}
