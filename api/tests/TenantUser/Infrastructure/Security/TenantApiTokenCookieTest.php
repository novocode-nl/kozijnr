<?php

namespace App\Tests\TenantUser\Infrastructure\Security;

use App\TenantUser\Infrastructure\Security\TenantApiTokenCookie;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Unit coverage for the cookie shape itself (KOZ-13 rework: HttpOnly
 * cookie-based tokens instead of localStorage) — the attributes that make
 * it safe to hand a bearer token to the browser via Set-Cookie: HttpOnly
 * (never JS-readable), Domain pinned to the base domain (so it reaches
 * api.<base> and the frontend hosts alike), SameSite=Lax, and secure left "auto"
 * (Symfony resolves it from the request's own scheme at send time).
 */
final class TenantApiTokenCookieTest extends TestCase
{
    public function testIssueProducesAnHttpOnlyCookieCarryingThePlainTextToken(): void
    {
        $cookie = TenantApiTokenCookie::issue('some-plain-text-token', 'kozijnr.localhost');

        self::assertSame(TenantApiTokenCookie::NAME, $cookie->getName());
        self::assertSame('some-plain-text-token', $cookie->getValue());
        self::assertTrue($cookie->isHttpOnly());
    }

    public function testIssueScopesTheCookieToTheGivenBaseDomain(): void
    {
        $cookie = TenantApiTokenCookie::issue('some-plain-text-token', 'kozijnr.localhost');

        self::assertSame('kozijnr.localhost', $cookie->getDomain());
    }

    public function testIssueUsesSameSiteLax(): void
    {
        $cookie = TenantApiTokenCookie::issue('some-plain-text-token', 'kozijnr.localhost');

        self::assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
    }

    public function testIssueLeavesSecureAsAutoRatherThanForcingItOnOrOff(): void
    {
        $cookie = TenantApiTokenCookie::issue('some-plain-text-token', 'kozijnr.localhost');

        // null = Symfony's "auto" mode: Response::prepare() turns this on
        // only when the inbound request is itself HTTPS, so the exact same
        // code works over plain-HTTP local dev (plain-HTTP *.kozijnr.localhost)
        // and a real HTTPS deployment.
        self::assertTrue(self::secureAttributeIsAuto($cookie));
    }

    public function testIssueSetsAFutureExpiry(): void
    {
        $cookie = TenantApiTokenCookie::issue('some-plain-text-token', 'kozijnr.localhost');

        self::assertGreaterThan(time(), $cookie->getExpiresTime());
        // Roughly 30 days out — matches TenantApiToken's own sliding-expiry
        // window, with a wide tolerance since both read time() independently.
        self::assertEqualsWithDelta(time() + 60 * 60 * 24 * 30, $cookie->getExpiresTime(), 5);
    }

    public function testClearProducesAnAlreadyExpiredCookieWithTheSameName(): void
    {
        $cookie = TenantApiTokenCookie::clear('kozijnr.localhost');

        self::assertSame(TenantApiTokenCookie::NAME, $cookie->getName());
        self::assertNull($cookie->getValue());
        self::assertLessThan(time(), $cookie->getExpiresTime());
    }

    private static function secureAttributeIsAuto(Cookie $cookie): bool
    {
        $reflection = new \ReflectionProperty(Cookie::class, 'secure');

        return $reflection->getValue($cookie) === null;
    }
}
