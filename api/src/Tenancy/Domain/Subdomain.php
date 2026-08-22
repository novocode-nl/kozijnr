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
     * The reserved super-admin subdomain label. Recognized by
     * TenantResolverListener as the tenant-independent super-admin domain,
     * and rejected by TenantName so it can never be provisioned as a tenant.
     */
    public const RESERVED_ADMIN = 'admin';

    /**
     * The reserved "api" subdomain label the REST API itself is served on.
     * No tenant context; a browser client on a sibling subdomain identifies
     * its context via the Origin header instead (see TenantResolverListener).
     */
    public const RESERVED_API = 'api';

    /**
     * Returns the subdomain label (e.g. "acme") for a host like
     * "acme.kozijnr.nl" given base domain "kozijnr.nl". Returns null when
     * the host is the base domain itself, or doesn't belong to it at all —
     * treated as the main domain rather than an error here; an unrecognized
     * subdomain still gets resolved against the tenants table by the caller.
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
