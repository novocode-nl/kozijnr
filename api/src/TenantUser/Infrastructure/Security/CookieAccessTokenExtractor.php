<?php

namespace App\TenantUser\Infrastructure\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\AccessToken\AccessTokenExtractorInterface;

/**
 * Extracts the tenant-user bearer token from the HttpOnly cookie it now
 * travels in (KOZ-13 rework — see TenantApiTokenCookie) instead of
 * Symfony's built-in header extractor (`Authorization: Bearer <token>`).
 * Wired in as the `tenant_users` firewall's token_extractors
 * (config/packages/security.yaml); TenantApiTokenHandler itself is
 * unchanged — Symfony already separates "where does the token come from"
 * (this class) from "is it valid" (the handler), so only the extraction
 * mechanism needed to change.
 */
final class CookieAccessTokenExtractor implements AccessTokenExtractorInterface
{
    public function extractAccessToken(Request $request): ?string
    {
        $token = $request->cookies->get(TenantApiTokenCookie::NAME);

        return \is_string($token) && $token !== '' ? $token : null;
    }
}
