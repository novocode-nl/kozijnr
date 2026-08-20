import { headers } from "next/headers"

/**
 * Which environment the current request belongs to: the super-admin
 * back office, or a tenant's own workspace.
 */
export type AppContext = "admin" | "tenant"

/**
 * The reserved admin subdomain label. Mirrors
 * `App\Tenancy\Domain\Subdomain::RESERVED_ADMIN` on the backend
 * (api/src/Tenancy/Domain/Subdomain.php) — kept as a plain string constant
 * here rather than importing anything cross-language, since this is the
 * one piece of shared vocabulary between the two.
 */
const ADMIN_SUBDOMAIN_LABEL = "admin"

/**
 * Resolves which app context a request belongs to from its Host header,
 * mirroring how the backend already distinguishes the admin subdomain from
 * any tenant subdomain (App\Tenancy\Infrastructure\TenantResolverListener /
 * AdminRouteGuardListener, see api/src/Tenancy/). Pure string logic, no
 * framework dependency, so it's trivially unit-testable — same spirit as
 * the backend's Subdomain::extractFrom.
 *
 * *** Deliberately provisional (KOZ-14) ***
 * KOZ-13/KOZ-8 shipped a tenant-user login (HttpOnly cookie) but there is
 * still no admin-facing frontend login/session at all — the super-admin
 * login from KOZ-8 is backend-only, session-based, on the `admin`
 * subdomain, with no UI in this app yet. Until that exists, there is no
 * real "logged-in context" for the frontend to inspect for the admin case,
 * so this falls back to the same signal the backend already uses to keep
 * the admin area separate: the subdomain. This function is intentionally
 * the *only* place that decision is made — swap its implementation (e.g.
 * to inspect an admin session cookie once one exists) and every caller
 * (the shared layout, `getAppContext` below) keeps working unchanged.
 *
 * Only the first host label is checked against the reserved "admin"
 * name (not a full base-domain match, unlike the backend's
 * `Subdomain::extractFrom`) — the frontend doesn't need full tenant
 * resolution here, only a binary admin/tenant split for which menu to
 * show. Anything that isn't recognized as the admin subdomain (a real
 * tenant subdomain, bare `localhost`, an unknown host, ...) defaults to
 * "tenant".
 *
 * KOZ-14 rework: `/dashboard` is now the single path for both contexts
 * (admin.<domein>/dashboard and <tenant>.<domein>/dashboard) — there is no
 * separate `/admin` route anymore, this function is what the shared
 * `/dashboard` page and its layout (app/(app)/layout.tsx) call to pick
 * which menu/content to render.
 */
export function resolveAppContext(host: string | null | undefined): AppContext {
  if (!host) {
    return "tenant"
  }

  const hostname = host.split(":")[0].toLowerCase()
  const firstLabel = hostname.split(".")[0]

  return firstLabel === ADMIN_SUBDOMAIN_LABEL ? "admin" : "tenant"
}

/**
 * Server-only convenience wrapper around `resolveAppContext` for use in
 * Server Components (the shared layout), reading the Host header of the
 * current request via `next/headers`.
 */
export async function getAppContext(): Promise<AppContext> {
  const headersList = await headers()
  return resolveAppContext(headersList.get("host"))
}
