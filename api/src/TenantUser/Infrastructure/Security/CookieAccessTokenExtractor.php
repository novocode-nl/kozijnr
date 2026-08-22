<?php

namespace App\TenantUser\Infrastructure\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\AccessToken\AccessTokenExtractorInterface;

/**
 * Extracts the tenant-user bearer token from the HttpOnly cookie it
 * travels in (see TenantApiTokenCookie), instead of Symfony's built-in
 * header extractor.
 */
final class CookieAccessTokenExtractor implements AccessTokenExtractorInterface
{
    public function extractAccessToken(Request $request): ?string
    {
        $token = $request->cookies->get(TenantApiTokenCookie::NAME);

        return \is_string($token) && $token !== '' ? $token : null;
    }
}
