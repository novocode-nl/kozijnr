<?php

namespace App\TenantUser\Infrastructure\Security;

use Symfony\Component\HttpFoundation\Cookie;

/**
 * Shapes the HttpOnly cookie the tenant-user bearer token now travels in
 * (KOZ-13 rework), replacing the earlier localStorage-based approach to
 * structurally remove the XSS token-exfiltration surface the previous
 * review already flagged as non-blocking.
 *
 * Attribute choices:
 * - HttpOnly: never readable from client-side JS — the entire point of
 *   this rework.
 * - Secure left `null` (Symfony's "auto" mode, see Cookie::create's
 *   `$secure` param): Response::prepare() enables it automatically once
 *   the *inbound* request is itself HTTPS, so the exact same code works
 *   over plain-HTTP local dev (Valet's `*.test` domains aren't served over
 *   TLS — see README.md "Local domains via Laravel Valet") and a real
 *   HTTPS deployment, without a separate flag to keep in sync.
 * - SameSite=Lax: from the browser's perspective this cookie is always
 *   first-party — the browser only ever talks to it via the Next.js
 *   frontend's own server-side proxy route (web/app/api/login/route.ts and
 *   web/app/api/logout/route.ts), which forwards the Set-Cookie/Cookie
 *   headers server-to-server; the browser itself never makes a cross-site
 *   request to the API. Lax is the strictest setting that still survives a
 *   plain top-level navigation (SameSite=Strict would drop the cookie on
 *   e.g. an external link landing on /dashboard), and SameSite=None would
 *   only be needed for an actually cross-site flow, which this isn't.
 * - No explicit Domain: a host-only cookie, scoped to exactly the tenant
 *   subdomain the browser is currently on — lines up with the
 *   subdomain-per-tenant scheme KOZ-6/KOZ-12 already use, with no extra
 *   configuration needed to keep the two in sync.
 * - 30-day lifetime: matches TenantApiToken's own sliding-expiry window
 *   (App\TenantUser\Domain\TenantApiToken::EXPIRY_PERIOD) — the cookie
 *   should never meaningfully outlive, or expire before, the token inside
 *   it.
 */
final class TenantApiTokenCookie
{
    public const NAME = 'tenant_api_token';

    private const LIFETIME_SECONDS = 60 * 60 * 24 * 30;

    public static function issue(string $plainTextToken): Cookie
    {
        return Cookie::create(
            name: self::NAME,
            value: $plainTextToken,
            expire: time() + self::LIFETIME_SECONDS,
            path: '/',
            domain: null,
            secure: null,
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_LAX,
        );
    }

    /**
     * An already-expired cookie with the same name/attributes as issue(),
     * to be set on the logout response so the browser actually discards it
     * (App\TenantUser\Infrastructure\Controller\LogoutController) — merely
     * deleting the token server-side leaves a stale cookie behind
     * otherwise.
     */
    public static function clear(): Cookie
    {
        return Cookie::create(
            name: self::NAME,
            value: null,
            expire: 1,
            path: '/',
            domain: null,
            secure: null,
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_LAX,
        );
    }
}
