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
 * This still resolves the context purely from the Host header, not from
 * an actual session — even though, as of KOZ-14 round 5, both contexts
 * now have a real frontend login/session (tenant: HttpOnly bearer-token
 * cookie from KOZ-13; admin: PHPSESSID session from the KOZ-8-backed
 * admin login form). The Host-based split is kept anyway: it's what
 * decides *which login form/menu to render before* a session exists in
 * the first place (see app/login/page.tsx), and proxy.ts's guard already
 * checks actual session validity separately for anything that needs real
 * authorization. This function is intentionally the *only* place the
 * context itself is decided — swap its implementation later if that ever
 * needs to change, and every caller (the shared layout, `getAppContext`
 * below) keeps working unchanged.
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
