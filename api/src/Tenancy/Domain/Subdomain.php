<?php

namespace App\Tenancy\Domain;

/**
 * Pure host-parsing logic: given the Host header of an incoming request and
 * the application's configured base domain, decides whether the request
 * targets a tenant subdomain or the main domain.
 *
 * No framework or infrastructure dependency on purpose — this is plain
 * string logic and is fully covered by unit tests without booting the
 * kernel.
 */
final class Subdomain
{
    /**
     * Returns the subdomain label (e.g. "acme") for a host like
     * "acme.kozijnr.nl" given base domain "kozijnr.nl".
     *
     * Returns null when the host is the base domain itself (main domain,
     * public schema) or when the host doesn't belong to the base domain at
     * all (treated as the main domain rather than an error case here — an
     * unrecognized *subdomain* under the base domain still gets resolved
     * against the tenants table by the caller, and 404s if unknown).
     */
    public static function extractFrom(string $host, string $baseDomain): ?string
    {
        $host = strtolower($host);
        $baseDomain = strtolower($baseDomain);

        if ($host === $baseDomain) {
            return null;
        }

        $suffix = '.' . $baseDomain;
        if (!str_ends_with($host, $suffix)) {
            return null;
        }

        $candidate = substr($host, 0, -strlen($suffix));

        return $candidate === '' ? null : $candidate;
    }
}
