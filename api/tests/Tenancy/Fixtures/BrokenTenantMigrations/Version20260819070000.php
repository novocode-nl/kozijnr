<?php

declare(strict_types=1);

namespace App\Tests\Tenancy\Fixtures\BrokenTenantMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Deliberately failing tenant migration, used only by
 * ProvisionTenantTest::testAFailedMigrationLeavesNoOrphanedSchemaBehind to
 * prove that `ProvisionTenant` cleans up the schema it just created when
 * the migration fails partway through — including a real Postgres error
 * raised mid-transaction (not just a PHP-level exception), which is what
 * actually risks leaving the connection in an aborted-transaction state.
 * Never referenced by the real tenant migration set.
 */
final class Version20260819070000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Test fixture: fails with a real Postgres error to simulate a mid-migration failure.';
    }

    public function up(Schema $schema): void
    {
        // A genuine SQL-level error (not a PHP exception thrown before any
        // query runs) so it fails exactly like the DbalExecutor's
        // executeResult() -> executeQuery() call would for a broken
        // migration, putting the underlying Postgres connection into an
        // aborted-transaction state until it's rolled back.
        $this->addSql('SELECT 1/0');
    }

    public function down(Schema $schema): void
    {
        // Never reached: this migration is only ever run "up" and only ever
        // fails.
    }
}
