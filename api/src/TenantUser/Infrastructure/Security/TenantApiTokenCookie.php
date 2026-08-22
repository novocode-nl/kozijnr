<?php

namespace App\TenantUser\Infrastructure\Security;

use Symfony\Component\HttpFoundation\Cookie;

/**
 * Shapes the HttpOnly cookie the tenant-user bearer token travels in.
 *
 * Attribute choices:
 * - HttpOnly: never readable from client-side JS.
 * - Secure left `null` (Symfony's "auto" mode): enabled automatically once
 *   the inbound request is itself HTTPS, so the same code works over
 *   plain-HTTP local dev and a real HTTPS deployment.
 * - SameSite=Lax: the browser calls this API cross-origin but same-site, so
 *   Lax cookies are sent along with `credentials: "include"` fetches while
 *   still surviving a plain top-level navigation.
 * - Domain = the base domain: the cookie is issued by api.<base> but must
 *   also reach the frontend on admin.<base>/<tenant>.<base>, whose
 *   server-side route guard checks for its presence. A host-only cookie
 *   would only ever travel back to api.<base>.
 * - 30-day lifetime: matches TenantApiToken's own sliding-expiry window.
 */
final class TenantApiTokenCookie
{
    public const NAME = 'tenant_api_token';

    private const LIFETIME_SECONDS = 60 * 60 * 24 * 30;

    public static function issue(string $plainTextToken, string $domain): Cookie
    {
        return Cookie::create(
            name: self::NAME,
            value: $plainTextToken,
            expire: time() + self::LIFETIME_SECONDS,
            path: '/',
            domain: $domain,
            secure: null,
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_LAX,
        );
    }

    /**
     * An already-expired cookie with the same name/attributes as issue(),
     * so the browser actually discards it on logout.
     */
    public static function clear(string $domain): Cookie
    {
        return Cookie::create(
            name: self::NAME,
            value: null,
            expire: 1,
            path: '/',
            domain: $domain,
            secure: null,
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_LAX,
        );
    }
}
