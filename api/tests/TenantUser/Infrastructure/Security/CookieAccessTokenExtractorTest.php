<?php

namespace App\Tests\TenantUser\Infrastructure\Security;

use App\TenantUser\Infrastructure\Security\CookieAccessTokenExtractor;
use App\TenantUser\Infrastructure\Security\TenantApiTokenCookie;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The `tenant_users` firewall's access_token authenticator extracts the
 * bearer token from the HttpOnly cookie (TenantApiTokenCookie) instead of
 * Symfony's built-in header extractor.
 */
final class CookieAccessTokenExtractorTest extends TestCase
{
    public function testExtractsTheTokenFromTheCookie(): void
    {
        $request = Request::create('/api/me');
        $request->cookies->set(TenantApiTokenCookie::NAME, 'the-token-value');

        $extractor = new CookieAccessTokenExtractor();

        self::assertSame('the-token-value', $extractor->extractAccessToken($request));
    }

    public function testReturnsNullWhenTheCookieIsAbsent(): void
    {
        $request = Request::create('/api/me');

        $extractor = new CookieAccessTokenExtractor();

        self::assertNull($extractor->extractAccessToken($request));
    }

    public function testReturnsNullWhenTheCookieIsEmpty(): void
    {
        $request = Request::create('/api/me');
        $request->cookies->set(TenantApiTokenCookie::NAME, '');

        $extractor = new CookieAccessTokenExtractor();

        self::assertNull($extractor->extractAccessToken($request));
    }

    /**
     * The whole point of this rework: an Authorization header alone (the
     * old mechanism) must no longer authenticate anything.
     */
    public function testIgnoresAnAuthorizationHeaderEvenIfPresent(): void
    {
        $request = Request::create('/api/me', server: ['HTTP_AUTHORIZATION' => 'Bearer some-header-token']);

        $extractor = new CookieAccessTokenExtractor();

        self::assertNull($extractor->extractAccessToken($request));
    }
}
