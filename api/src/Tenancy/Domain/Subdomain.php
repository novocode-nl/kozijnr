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
     * The reserved super-admin subdomain label (admin.kozijnr.nl in
     * production, admin.localhost locally) — recognized by
     * TenantResolverListener as the tenant-independent super-admin domain
     * rather than an unknown tenant, and rejected by TenantName so it can
     * never be provisioned as an actual tenant.
     */
    public const RESERVED_ADMIN = 'admin';

    /**
     * The reserved "api" subdomain label (api.kozijnr.nl in production,
     * api.kozijnr.localhost locally behind the nginx proxy): the hostname
     * the REST API itself is served on. No tenant context, no admin
     * context, stays on the public schema — unless a browser client on a
     * sibling subdomain identifies its context via the Origin header (see
     * TenantResolverListener).
     * Recognized by TenantResolverListener exactly like RESERVED_ADMIN, and
     * rejected by TenantName so it can never be provisioned as an actual
     * tenant either.
     */
    public const RESERVED_API = 'api';

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
