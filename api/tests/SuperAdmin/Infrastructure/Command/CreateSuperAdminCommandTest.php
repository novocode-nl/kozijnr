<?php

namespace App\Tests\SuperAdmin\Infrastructure\Command;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateSuperAdminCommandTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $this->connection()->executeStatement('DELETE FROM public.users');
    }

    protected function tearDown(): void
    {
        $this->connection()->executeStatement('DELETE FROM public.users');

        parent::tearDown();
    }

    public function testCreatesASuperAdminWithAHashedPassword(): void
    {
        $exitCode = $this->runCreate('admin@kozijnr.nl', 'secret123');

        self::assertSame(0, $exitCode);

        $rows = $this->connection()->fetchAllAssociative('SELECT email, password FROM public.users');
        self::assertCount(1, $rows);
        self::assertSame('admin@kozijnr.nl', $rows[0]['email']);
        self::assertNotSame('secret123', $rows[0]['password']);
    }

    public function testRejectsCreatingADuplicateEmail(): void
    {
        $this->runCreate('admin@kozijnr.nl', 'secret123');

        $exitCode = $this->runCreate('admin@kozijnr.nl', 'other-password');

        self::assertNotSame(0, $exitCode);
        self::assertCount(1, $this->connection()->fetchAllAssociative('SELECT * FROM public.users'));
    }

    private function runCreate(string $email, string $password): int
    {
        $application = new Application(self::$kernel);
        $command = $application->find('super-admin:create');
        $tester = new CommandTester($command);

        return $tester->execute(['email' => $email, 'password' => $password]);
    }

    private function connection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }
}
