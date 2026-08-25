<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seeds the `user:list` (view the admin user overview) and `user:create`
 * (create a new admin user from the admin UI) permissions introduced by
 * KOZ-30, and grants both to ROLE_SUPER_ADMIN.
 */
final class Version20260824120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed the user:list and user:create permissions and grant them to ROLE_SUPER_ADMIN.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO permissions (name) VALUES ('user:list'), ('user:create')");
        $this->addSql(
            "INSERT INTO role_permissions (role_id, permission_id) "
            . "SELECT r.id, p.id FROM roles r, permissions p "
            . "WHERE r.name = 'ROLE_SUPER_ADMIN' AND p.name IN ('user:list', 'user:create')"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "DELETE FROM role_permissions WHERE permission_id IN "
            . "(SELECT id FROM permissions WHERE name IN ('user:list', 'user:create'))"
        );
        $this->addSql("DELETE FROM permissions WHERE name IN ('user:list', 'user:create')");
    }
}
