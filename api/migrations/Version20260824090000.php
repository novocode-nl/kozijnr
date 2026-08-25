<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seeds the `tenant:users:create` permission (KOZ-31: "Gebruiker
 * toevoegen" action on a tenant's "Gebruikers" tab) and grants it to
 * ROLE_SUPER_ADMIN, alongside the existing tenant:users:list permission
 * from Version20260822110000. Kept distinct from tenant:users:list so
 * listing and creating tenant users can be granted independently.
 */
final class Version20260824090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed the tenant:users:create permission and grant it to ROLE_SUPER_ADMIN.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO permissions (name) VALUES ('tenant:users:create')");
        $this->addSql(
            "INSERT INTO role_permissions (role_id, permission_id) "
            . "SELECT r.id, p.id FROM roles r, permissions p "
            . "WHERE r.name = 'ROLE_SUPER_ADMIN' AND p.name = 'tenant:users:create'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "DELETE FROM role_permissions WHERE permission_id = "
            . "(SELECT id FROM permissions WHERE name = 'tenant:users:create')"
        );
        $this->addSql("DELETE FROM permissions WHERE name = 'tenant:users:create'");
    }
}
