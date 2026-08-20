<?php

namespace App\Tests\TenantUser\Infrastructure\Security;

use App\TenantUser\Domain\TenantApiToken;
use App\TenantUser\Domain\TenantApiTokenRepositoryInterface;
use App\TenantUser\Domain\TenantUser;
use App\TenantUser\Infrastructure\Security\TenantApiTokenHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

final class TenantApiTokenHandlerTest extends TestCase
{
    public function testResolvesAUserBadgeForAKnownToken(): void
    {
        $user = new TenantUser('user@acme.test', 'hashed-password', ['ROLE_TENANT_USER']);
        $issuedToken = TenantApiToken::issueFor($user);

        $tokenRepository = $this->createMock(TenantApiTokenRepositoryInterface::class);
        $tokenRepository->expects(self::once())
            ->method('findByTokenHash')
            ->with(TenantApiToken::hashFor($issuedToken->getPlainTextToken()))
            ->willReturn($issuedToken);

        $handler = new TenantApiTokenHandler($tokenRepository);

        $badge = $handler->getUserBadgeFrom($issuedToken->getPlainTextToken());

        self::assertSame('user@acme.test', $badge->getUserIdentifier());
        self::assertSame($user, $badge->getUser());
    }

    public function testThrowsOnAnUnknownToken(): void
    {
        $tokenRepository = $this->createMock(TenantApiTokenRepositoryInterface::class);
        $tokenRepository->expects(self::once())->method('findByTokenHash')->willReturn(null);

        $handler = new TenantApiTokenHandler($tokenRepository);

        $this->expectException(BadCredentialsException::class);

        $handler->getUserBadgeFrom('does-not-exist');
    }

    public function testRenewsTheTokenExpiryOnEverySuccessfulUse(): void
    {
        $user = new TenantUser('user@acme.test', 'hashed-password', ['ROLE_TENANT_USER']);
        $issuedToken = TenantApiToken::issueFor($user);
        $originalExpiry = $issuedToken->getExpiresAt();

        $tokenRepository = $this->createMock(TenantApiTokenRepositoryInterface::class);
        $tokenRepository->expects(self::once())->method('findByTokenHash')->willReturn($issuedToken);
        $tokenRepository->expects(self::once())->method('save')->with($issuedToken);

        $handler = new TenantApiTokenHandler($tokenRepository);
        $handler->getUserBadgeFrom($issuedToken->getPlainTextToken());

        self::assertGreaterThan($originalExpiry, $issuedToken->getExpiresAt());
    }

    /**
     * Same generic exception, same message, as an unknown token (KOZ-15
     * DoD): a caller must not be able to tell an expired token apart from
     * one that was never issued.
     */
    public function testThrowsTheSameGenericExceptionOnAnExpiredTokenAsOnAnUnknownToken(): void
    {
        $user = new TenantUser('user@acme.test', 'hashed-password', ['ROLE_TENANT_USER']);
        $expiredToken = TenantApiToken::issueFor($user);
        $expiredToken->renewExpiry((new \DateTimeImmutable())->modify('-31 days'));

        $tokenRepository = $this->createMock(TenantApiTokenRepositoryInterface::class);
        $tokenRepository->expects(self::once())->method('findByTokenHash')->willReturn($expiredToken);
        $tokenRepository->expects(self::never())->method('save');

        $handler = new TenantApiTokenHandler($tokenRepository);

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Invalid or expired token.');

        $handler->getUserBadgeFrom($expiredToken->getPlainTextToken());
    }
}
