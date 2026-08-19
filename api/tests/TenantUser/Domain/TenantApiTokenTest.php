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
}
