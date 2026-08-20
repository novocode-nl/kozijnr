<?php

declare(strict_types=1);

namespace App\Migrations\Tenant;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds sliding-expiry to tenant_user_api_tokens (KOZ-15): every issued
 * token now carries an expires_at, set to issue-time + 30 days and pushed
 * out another 30 days on every successful use (see
 * App\TenantUser\Domain\TenantApiToken and
 * App\TenantUser\Infrastructure\Security\TenantApiTokenHandler).
 *
 * Any pre-existing row (there shouldn't be any real ones yet, this table
 * was only just introduced by KOZ-11) is backfilled to created_at + 30
 * days rather than left NULL, so the column can be NOT NULL from the
 * start and every row in the domain model always has a real expiry.
 */
final class Version20260820090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add expires_at to tenant_user_api_tokens (sliding expiry, KOZ-15).';
    }

    public function up(Schema $schema): void
    {
        // Postgres doesn't allow a column reference in a column DEFAULT
        // expression, so this backfills in three steps: add nullable, fill
        // from the existing created_at, then enforce NOT NULL.
        $this->addSql('ALTER TABLE tenant_user_api_tokens ADD expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql("UPDATE tenant_user_api_tokens SET expires_at = created_at + INTERVAL '30 days'");
        $this->addSql('ALTER TABLE tenant_user_api_tokens ALTER COLUMN expires_at SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_user_api_tokens DROP COLUMN expires_at');
    }
}
