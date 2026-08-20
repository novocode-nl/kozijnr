<?php

namespace App\Tests\TenantUser\Domain;

use App\TenantUser\Domain\TenantApiToken;
use App\TenantUser\Domain\TenantUser;
use PHPUnit\Framework\TestCase;

final class TenantApiTokenTest extends TestCase
{
    public function testIssueForGeneratesAPlainTextTokenAndStoresOnlyItsHash(): void
    {
        $user = new TenantUser('user@acme.test', 'hashed-password', ['ROLE_TENANT_USER']);

        $token = TenantApiToken::issueFor($user);

        self::assertSame($user, $token->getTenantUser());
        self::assertNotSame('', $token->getPlainTextToken());
        // The stored hash must never equal the plaintext token, otherwise a
        // leaked database row would directly hand out working tokens.
        self::assertNotSame($token->getPlainTextToken(), $token->getTokenHash());
        self::assertSame(hash('sha256', $token->getPlainTextToken()), $token->getTokenHash());
    }

    public function testEachIssuedTokenIsUnique(): void
    {
        $user = new TenantUser('user@acme.test', 'hashed-password', ['ROLE_TENANT_USER']);

        $tokenA = TenantApiToken::issueFor($user);
        $tokenB = TenantApiToken::issueFor($user);

        self::assertNotSame($tokenA->getPlainTextToken(), $tokenB->getPlainTextToken());
        self::assertNotSame($tokenA->getTokenHash(), $tokenB->getTokenHash());
    }

    public function testHashForHashesAGivenPlainTextTokenTheSameWayAsIssueFor(): void
    {
        self::assertSame(hash('sha256', 'some-plain-token'), TenantApiToken::hashFor('some-plain-token'));
    }

    public function testIssueForSetsAnExpiryThirtyDaysOut(): void
    {
        $user = new TenantUser('user@acme.test', 'hashed-password', ['ROLE_TENANT_USER']);

        $before = new \DateTimeImmutable();
        $token = TenantApiToken::issueFor($user);
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before->modify('+30 days'), $token->getExpiresAt());
        self::assertLessThanOrEqual($after->modify('+30 days'), $token->getExpiresAt());
    }

    public function testIsExpiredIsFalseBeforeTheExpiryMomentAndTrueAfterIt(): void
    {
        $user = new TenantUser('user@acme.test', 'hashed-password', ['ROLE_TENANT_USER']);
        $token = TenantApiToken::issueFor($user);

        self::assertFalse($token->isExpired($token->getExpiresAt()->modify('-1 second')));
        self::assertTrue($token->isExpired($token->getExpiresAt()->modify('+1 second')));
    }

    public function testRenewExpiryPushesTheExpiryThirtyDaysFromTheGivenMoment(): void
    {
        $user = new TenantUser('user@acme.test', 'hashed-password', ['ROLE_TENANT_USER']);
        $token = TenantApiToken::issueFor($user);

        $now = new \DateTimeImmutable('2026-01-01 12:00:00');
        $token->renewExpiry($now);

        self::assertEquals($now->modify('+30 days'), $token->getExpiresAt());
    }
}
