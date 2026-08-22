import { headers } from "next/headers"

/**
 * Which environment the current request belongs to: the super-admin
 * back office, or a tenant's own workspace.
 */
export type AppContext = "admin" | "tenant"

/** Mirrors `Subdomain::RESERVED_ADMIN` on the backend. */
const ADMIN_SUBDOMAIN_LABEL = "admin"

/**
 * Resolves which app context a request belongs to from its Host header,
 * mirroring how the backend distinguishes the admin subdomain from any
 * tenant subdomain. Deliberately provisional: it decides purely from the
 * Host header, not an actual session — it's what picks which login
 * form/menu to render *before* a session exists; proxy.ts's guard checks
 * real session validity separately wherever that's needed. This is
 * intentionally the only place the context is decided, so callers keep
 * working unchanged if the implementation ever needs to change.
 *
 * Only the first host label is checked against the reserved "admin" name
 * (not a full base-domain match, unlike the backend's
 * `Subdomain::extractFrom`) — the frontend only needs a binary admin/tenant
 * split for which menu to show. Anything not recognized as the admin
 * subdomain defaults to "tenant".
 *
 * `/` is the single home path for both contexts — there is no separate
 * `/admin` route, and `/dashboard` no longer exists as its own route
 * either, it always redirects to `/` (proxy.ts).
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
