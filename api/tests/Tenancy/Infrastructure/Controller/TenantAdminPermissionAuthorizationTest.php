<?php

namespace App\Tests\Tenancy\Infrastructure\Controller;

use App\User\Domain\PasswordHasherInterface;
use App\User\Domain\Permission;
use App\User\Domain\Role;
use App\User\Domain\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Proves that authorization on the admin tenant API is decided by the
 * authenticated user's *permissions* (tenant:list / tenant:create /
 * tenant:update / tenant:archive / tenant:users:list), not by a
 * ROLE_SUPER_ADMIN role-name comparison. Users below can authenticate on
 * the `super_admin` firewall but are assigned custom roles carrying only
 * the permission(s) under test.
 */
final class TenantAdminPermissionAuthorizationTest extends WebTestCase
{
    private const ADMIN_HOST = 'admin.localhost';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->resetDatabase();
    }

    protected function tearDown(): void
    {
        $this->resetDatabase();

        parent::tearDown();
    }

    public function testAUserWithOnlyTheListPermissionCanListButNotCreateTenants(): void
    {
        $this->createUserWithPermissions('list-only@kozijnr.nl', 'super-secret-123', ['tenant:list']);
        $this->login('list-only@kozijnr.nl', 'super-secret-123');

        $this->client->request('GET', '/api/admin/tenants', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        self::assertResponseIsSuccessful();

        $this->createTenantRequest('Acme', 'acme');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAUserWithOnlyTheCreatePermissionCanCreateButNotListTenants(): void
    {
        $this->createUserWithPermissions('create-only@kozijnr.nl', 'super-secret-123', ['tenant:create']);
        $this->login('create-only@kozijnr.nl', 'super-secret-123');

        $this->client->request('GET', '/api/admin/tenants', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        self::assertResponseStatusCodeSame(403);

        $this->createTenantRequest('Acme', 'acme');
        self::assertResponseStatusCodeSame(201);
    }

    public function testAUserWithNeitherPermissionCanDoNeither(): void
    {
        $this->createUserWithPermissions('no-permissions@kozijnr.nl', 'super-secret-123', []);
        $this->login('no-permissions@kozijnr.nl', 'super-secret-123');

        $this->client->request('GET', '/api/admin/tenants', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        self::assertResponseStatusCodeSame(403);

        $this->createTenantRequest('Acme', 'acme');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAUserWithoutTheUpdatePermissionCannotUpdateATenant(): void
    {
        $this->createUserWithPermissions('list-only@kozijnr.nl', 'super-secret-123', ['tenant:list']);
        $this->login('list-only@kozijnr.nl', 'super-secret-123');

        $this->client->request('PATCH', '/api/admin/tenants/acme', server: [
            'HTTP_HOST' => self::ADMIN_HOST,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['name' => 'Acme', 'slug' => 'acme-bv']));
        self::assertResponseStatusCodeSame(403);
    }

    public function testAUserWithTheUpdatePermissionCanUpdateATenant(): void
    {
        $this->createUserWithPermissions('update-only@kozijnr.nl', 'super-secret-123', ['tenant:create', 'tenant:update']);
        $this->login('update-only@kozijnr.nl', 'super-secret-123');

        $this->createTenantRequest('Acme', 'acme');
        self::assertResponseStatusCodeSame(201);

        $this->client->request('PATCH', '/api/admin/tenants/acme', server: [
            'HTTP_HOST' => self::ADMIN_HOST,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['name' => 'Acme', 'slug' => 'acme-bv']));
        self::assertResponseIsSuccessful();
    }

    public function testAUserWithoutTheArchivePermissionCannotArchiveOrUnarchiveATenant(): void
    {
        $this->createUserWithPermissions('no-archive@kozijnr.nl', 'super-secret-123', ['tenant:create']);
        $this->login('no-archive@kozijnr.nl', 'super-secret-123');

        $this->createTenantRequest('Acme', 'acme');

        $this->client->request('POST', '/api/admin/tenants/acme/archive', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/api/admin/tenants/acme/unarchive', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAUserWithTheArchivePermissionCanArchiveAndUnarchiveATenant(): void
    {
        $this->createUserWithPermissions('archive-only@kozijnr.nl', 'super-secret-123', ['tenant:create', 'tenant:archive']);
        $this->login('archive-only@kozijnr.nl', 'super-secret-123');

        $this->createTenantRequest('Acme', 'acme');

        $this->client->request('POST', '/api/admin/tenants/acme/archive', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        self::assertResponseIsSuccessful();

        $this->client->request('POST', '/api/admin/tenants/acme/unarchive', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        self::assertResponseIsSuccessful();
    }

    public function testAUserWithoutTheUsersListPermissionCannotListATenantsUsers(): void
    {
        $this->createUserWithPermissions('no-users-list@kozijnr.nl', 'super-secret-123', ['tenant:create']);
        $this->login('no-users-list@kozijnr.nl', 'super-secret-123');

        $this->createTenantRequest('Acme', 'acme');

        $this->client->request('GET', '/api/admin/tenants/acme/users', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAUserWithTheUsersListPermissionCanListATenantsUsers(): void
    {
        $this->createUserWithPermissions('users-list@kozijnr.nl', 'super-secret-123', ['tenant:create', 'tenant:users:list']);
        $this->login('users-list@kozijnr.nl', 'super-secret-123');

        $this->createTenantRequest('Acme', 'acme');

        $this->client->request('GET', '/api/admin/tenants/acme/users', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        self::assertResponseIsSuccessful();
    }

    /**
     * @param list<string> $permissionNames Must already exist (seeded by
     *                                       migration), e.g. 'tenant:list'.
     */
    private function createUserWithPermissions(string $email, string $password, array $permissionNames): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $role = new Role('ROLE_TEST_' . strtoupper(bin2hex(random_bytes(4))));

        foreach ($permissionNames as $permissionName) {
            $permission = $entityManager->getRepository(Permission::class)->findOneBy(['name' => $permissionName]);
            self::assertNotNull($permission, sprintf('Expected permission "%s" to already be seeded.', $permissionName));
            $role->addPermission($permission);
        }

        $entityManager->persist($role);

        $passwordHasher = self::getContainer()->get(PasswordHasherInterface::class);
        $placeholder = new User($email, 'placeholder', [$role]);
        $hashedPassword = $passwordHasher->hash($placeholder, $password);

        $user = new User($email, $hashedPassword, [$role]);
        $entityManager->persist($user);
        $entityManager->flush();
    }

    private function createTenantRequest(string $name, string $slug): void
    {
        $this->client->request('POST', '/api/admin/tenants', server: [
            'HTTP_HOST' => self::ADMIN_HOST,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['name' => $name, 'slug' => $slug]));
    }

    private function login(string $email, string $password): void
    {
        $this->client->request('POST', '/api/admin/login', server: [
            'HTTP_HOST' => self::ADMIN_HOST,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => $email, 'password' => $password]));
    }

    private function resetDatabase(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $connection->executeStatement('DROP SCHEMA IF EXISTS tenant_acme CASCADE');
        $connection->executeStatement('DROP SCHEMA IF EXISTS tenant_acme_bv CASCADE');
        $connection->executeStatement('DELETE FROM public.tenants');
        $connection->executeStatement('DELETE FROM public.user_roles');
        $connection->executeStatement('DELETE FROM public.users');
        $connection->executeStatement("DELETE FROM public.roles WHERE name LIKE 'ROLE_TEST_%'");
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
