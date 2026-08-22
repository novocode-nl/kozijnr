<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seeds the `tenant:archive` (archive/unarchive a tenant) and
 * `tenant:users:list` (view a tenant's users tab) permissions introduced
 * by KOZ-27, and grants both to ROLE_SUPER_ADMIN alongside the existing
 * tenant:list/tenant:create/tenant:update permissions.
 */
final class Version20260822110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed the tenant:archive and tenant:users:list permissions and grant them to ROLE_SUPER_ADMIN.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO permissions (name) VALUES ('tenant:archive'), ('tenant:users:list')");
        $this->addSql(
            "INSERT INTO role_permissions (role_id, permission_id) "
            . "SELECT r.id, p.id FROM roles r, permissions p "
            . "WHERE r.name = 'ROLE_SUPER_ADMIN' AND p.name IN ('tenant:archive', 'tenant:users:list')"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "DELETE FROM role_permissions WHERE permission_id IN "
            . "(SELECT id FROM permissions WHERE name IN ('tenant:archive', 'tenant:users:list'))"
        );
        $this->addSql("DELETE FROM permissions WHERE name IN ('tenant:archive', 'tenant:users:list')");
    }
}
