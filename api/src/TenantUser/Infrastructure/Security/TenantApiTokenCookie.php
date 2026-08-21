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
 *   over plain-HTTP local dev (the nginx `*.kozijnr.localhost` domains
 *   aren't served over TLS — see README.md "Local domains via nginx") and a real
 *   HTTPS deployment, without a separate flag to keep in sync.
 * - SameSite=Lax: the browser client (admin.<base> / <tenant>.<base>)
 *   calls this API cross-origin on api.<base>, but that is still the same
 *   *site* (same registrable domain), so Lax cookies are sent along with
 *   `credentials: "include"` fetches. Lax is the strictest setting that
 *   still survives a plain top-level navigation (SameSite=Strict would drop
 *   the cookie on e.g. an external link landing on /dashboard), and
 *   SameSite=None would only be needed for an actually cross-site flow,
 *   which this isn't.
 * - No explicit Domain: a host-only cookie, scoped to exactly the host it
 *   was issued on — api.<base>, the only host the browser client ever
 *   sends API requests to. The tenant itself is carried by the request's
 *   Origin (see TenantResolverListener), not by the cookie's scope.
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
