<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seeds the `tenant:update` permission (tenant-edit screen, KOZ-25) and
 * grants it to ROLE_SUPER_ADMIN, alongside the existing tenant:list/
 * tenant:create permissions from Version20260819170000.
 */
final class Version20260822090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed the tenant:update permission and grant it to ROLE_SUPER_ADMIN.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO permissions (name) VALUES ('tenant:update')");
        $this->addSql(
            "INSERT INTO role_permissions (role_id, permission_id) "
            . "SELECT r.id, p.id FROM roles r, permissions p "
            . "WHERE r.name = 'ROLE_SUPER_ADMIN' AND p.name = 'tenant:update'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "DELETE FROM role_permissions WHERE permission_id = "
            . "(SELECT id FROM permissions WHERE name = 'tenant:update')"
        );
        $this->addSql("DELETE FROM permissions WHERE name = 'tenant:update'");
    }
}
