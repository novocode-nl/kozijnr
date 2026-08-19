<?php

namespace App\Tests\TenantUser\Application;

use App\TenantUser\Application\LoginTenantUser;
use App\TenantUser\Domain\Exception\InvalidCredentialsException;
use App\TenantUser\Domain\PasswordHasherInterface;
use App\TenantUser\Domain\TenantApiTokenRepositoryInterface;
use App\TenantUser\Domain\TenantUser;
use App\TenantUser\Domain\TenantUserRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class LoginTenantUserTest extends TestCase
{
    public function testReturnsAPlainTextTokenOnValidCredentials(): void
    {
        $user = new TenantUser('user@acme.test', 'hashed-password', ['ROLE_TENANT_USER']);

        $userRepository = $this->createMock(TenantUserRepositoryInterface::class);
        $userRepository->expects(self::once())->method('findByEmail')->with('user@acme.test')->willReturn($user);

        $passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $passwordHasher->expects(self::once())->method('verify')->with($user, 'correct-password')->willReturn(true);

        $tokenRepository = $this->createMock(TenantApiTokenRepositoryInterface::class);
        $tokenRepository->expects(self::once())->method('add');

        $login = new LoginTenantUser($userRepository, $tokenRepository, $passwordHasher);

        $token = $login('user@acme.test', 'correct-password');

        self::assertNotSame('', $token);
    }

    public function testThrowsInvalidCredentialsWhenTheEmailIsUnknown(): void
    {
        $userRepository = $this->createMock(TenantUserRepositoryInterface::class);
        $userRepository->expects(self::once())->method('findByEmail')->willReturn(null);

        $passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $passwordHasher->expects(self::never())->method('verify');

        $tokenRepository = $this->createMock(TenantApiTokenRepositoryInterface::class);
        $tokenRepository->expects(self::never())->method('add');

        $login = new LoginTenantUser($userRepository, $tokenRepository, $passwordHasher);

        $this->expectException(InvalidCredentialsException::class);

        $login('unknown@acme.test', 'whatever');
    }

    public function testThrowsInvalidCredentialsWhenThePasswordIsWrong(): void
    {
        $user = new TenantUser('user@acme.test', 'hashed-password', ['ROLE_TENANT_USER']);

        $userRepository = $this->createMock(TenantUserRepositoryInterface::class);
        $userRepository->expects(self::once())->method('findByEmail')->willReturn($user);

        $passwordHasher = $this->createMock(PasswordHasherInterface::class);
        $passwordHasher->expects(self::once())->method('verify')->willReturn(false);

        $tokenRepository = $this->createMock(TenantApiTokenRepositoryInterface::class);
        $tokenRepository->expects(self::never())->method('add');

        $login = new LoginTenantUser($userRepository, $tokenRepository, $passwordHasher);

        $this->expectException(InvalidCredentialsException::class);

        $login('user@acme.test', 'wrong-password');
    }

    public function testUnknownEmailAndWrongPasswordRaiseTheExactSameExceptionMessage(): void
    {
        $user = new TenantUser('user@acme.test', 'hashed-password', ['ROLE_TENANT_USER']);

        $userRepositoryKnown = $this->createMock(TenantUserRepositoryInterface::class);
        $userRepositoryKnown->expects(self::once())->method('findByEmail')->willReturn($user);
        $passwordHasherWrong = $this->createMock(PasswordHasherInterface::class);
        $passwordHasherWrong->expects(self::once())->method('verify')->willReturn(false);
        $tokenRepositoryA = $this->createMock(TenantApiTokenRepositoryInterface::class);
        $tokenRepositoryA->expects(self::never())->method('add');

        $loginWrongPassword = new LoginTenantUser($userRepositoryKnown, $tokenRepositoryA, $passwordHasherWrong);

        $userRepositoryUnknown = $this->createMock(TenantUserRepositoryInterface::class);
        $userRepositoryUnknown->expects(self::once())->method('findByEmail')->willReturn(null);
        $passwordHasherUnused = $this->createMock(PasswordHasherInterface::class);
        $passwordHasherUnused->expects(self::never())->method('verify');
        $tokenRepositoryB = $this->createMock(TenantApiTokenRepositoryInterface::class);
        $tokenRepositoryB->expects(self::never())->method('add');

        $loginUnknownEmail = new LoginTenantUser($userRepositoryUnknown, $tokenRepositoryB, $passwordHasherUnused);

        $messageForWrongPassword = null;
        try {
            $loginWrongPassword('user@acme.test', 'wrong-password');
        } catch (InvalidCredentialsException $exception) {
            $messageForWrongPassword = $exception->getMessage();
        }

        $messageForUnknownEmail = null;
        try {
            $loginUnknownEmail('unknown@acme.test', 'whatever');
        } catch (InvalidCredentialsException $exception) {
            $messageForUnknownEmail = $exception->getMessage();
        }

        self::assertNotNull($messageForWrongPassword);
        self::assertSame($messageForWrongPassword, $messageForUnknownEmail);
    }
}
