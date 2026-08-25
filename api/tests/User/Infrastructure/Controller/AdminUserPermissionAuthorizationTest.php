<?php

namespace App\Tests\User\Infrastructure\Controller;

use App\User\Domain\PasswordHasherInterface;
use App\User\Domain\Permission;
use App\User\Domain\Role;
use App\User\Domain\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Proves that authorization on the admin user API (KOZ-30) is decided by
 * the authenticated user's *permissions* (user:list / user:create), not by
 * a ROLE_SUPER_ADMIN role-name comparison. Modeled directly on
 * TenantAdminPermissionAuthorizationTest.
 */
final class AdminUserPermissionAuthorizationTest extends WebTestCase
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

    public function testAUserWithOnlyTheListPermissionCanListButNotCreateAdminUsers(): void
    {
        $this->createUserWithPermissions('list-only@kozijnr.nl', 'super-secret-123', ['user:list']);
        $this->login('list-only@kozijnr.nl', 'super-secret-123');

        $this->client->request('GET', '/api/admin/users', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        self::assertResponseIsSuccessful();

        $this->createAdminUserRequest('someone@kozijnr.nl');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAUserWithOnlyTheCreatePermissionCanCreateButNotListAdminUsers(): void
    {
        $this->createUserWithPermissions('create-only@kozijnr.nl', 'super-secret-123', ['user:create']);
        $this->login('create-only@kozijnr.nl', 'super-secret-123');

        $this->client->request('GET', '/api/admin/users', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        self::assertResponseStatusCodeSame(403);

        $this->createAdminUserRequest('someone@kozijnr.nl');
        self::assertResponseStatusCodeSame(201);
    }

    public function testAUserWithNeitherPermissionCanDoNeither(): void
    {
        $this->createUserWithPermissions('no-permissions@kozijnr.nl', 'super-secret-123', []);
        $this->login('no-permissions@kozijnr.nl', 'super-secret-123');

        $this->client->request('GET', '/api/admin/users', server: ['HTTP_HOST' => self::ADMIN_HOST]);
        self::assertResponseStatusCodeSame(403);

        $this->createAdminUserRequest('someone@kozijnr.nl');
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @param list<string> $permissionNames Must already exist (seeded by
     *                                       migration), e.g. 'user:list'.
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

    private function createAdminUserRequest(string $email): void
    {
        $this->client->request('POST', '/api/admin/users', server: [
            'HTTP_HOST' => self::ADMIN_HOST,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => $email]));
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
        $connection->executeStatement('DELETE FROM public.user_roles');
        $connection->executeStatement('DELETE FROM public.users');
        $connection->executeStatement("DELETE FROM public.roles WHERE name LIKE 'ROLE_TEST_%'");
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
