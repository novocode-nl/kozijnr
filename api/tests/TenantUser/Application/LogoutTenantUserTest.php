<?php

namespace App\Tests\TenantUser\Application;

use App\TenantUser\Application\LogoutTenantUser;
use App\TenantUser\Domain\TenantApiToken;
use App\TenantUser\Domain\TenantApiTokenRepositoryInterface;
use App\TenantUser\Domain\TenantUser;
use PHPUnit\Framework\TestCase;

final class LogoutTenantUserTest extends TestCase
{
    public function testRevokesTheTokenMatchingTheGivenPlainTextToken(): void
    {
        $user = new TenantUser('user@acme.test', 'hashed-password', ['ROLE_TENANT_USER']);
        $issuedToken = TenantApiToken::issueFor($user);

        $tokenRepository = $this->createMock(TenantApiTokenRepositoryInterface::class);
        $tokenRepository->expects(self::once())
            ->method('findByTokenHash')
            ->with(TenantApiToken::hashFor($issuedToken->getPlainTextToken()))
            ->willReturn($issuedToken);
        $tokenRepository->expects(self::once())->method('remove')->with($issuedToken);

        $logout = new LogoutTenantUser($tokenRepository);

        $logout($issuedToken->getPlainTextToken());
    }

    public function testIsANoOpWhenTheTokenIsAlreadyGone(): void
    {
        $tokenRepository = $this->createMock(TenantApiTokenRepositoryInterface::class);
        $tokenRepository->expects(self::once())->method('findByTokenHash')->willReturn(null);
        $tokenRepository->expects(self::never())->method('remove');

        $logout = new LogoutTenantUser($tokenRepository);

        $logout('does-not-exist');
    }
}
