import { NextRequest, NextResponse } from "next/server"

import { apiBaseUrl } from "@/lib/api-base-url"
import { sendBackendRequest } from "@/lib/backend-request"
import { TENANT_TOKEN_COOKIE_NAME } from "@/lib/auth/token-cookie"
import { resolveAppContext } from "@/lib/context/app-context"
import { LOCALE_COOKIE_NAME, isSupportedLocale } from "@/lib/i18n/locale"
import { REDIRECT_PARAM, buildRedirectTarget } from "@/lib/navigation/safe-redirect"

/**
 * Server-side route guard (Next.js `proxy.ts` convention; runs on the
 * Node.js runtime, needed for the `node:http` call in `hasValidAdminSession`).
 *
 * Every route on either subdomain sits behind a login:
 * - tenant context: the HttpOnly token cookie merely has to be present —
 *   its validity is checked by the API on every real call anyway.
 * - admin context: the session is an opaque Symfony session cookie this
 *   frontend can't introspect, so the API is asked (GET /api/admin/me)
 *   whether it's still valid. Fails closed on any error or timeout.
 *
 * `/login` is the single login path for both contexts; an already
 * authenticated visitor is sent on to /. `/dashboard` no longer exists as
 * its own route — it always redirects to / before the login-status check,
 * regardless of context.
 *
 * The login-status check below already covers "unknown route, not logged
 * in": since every path other than /login redirects when there's no valid
 * session, a nonexistent route bounces to /login without ever reaching
 * Next's routing/notFound(). Order matters — only a session-holding
 * visitor ever reaches Next's route resolution, where
 * app/(app)/not-found.tsx renders the branded 404.
 *
 * A bounce to `/login` carries the originally requested page as
 * `?redirect=<path>` (lib/navigation/safe-redirect.ts) so the login form
 * can send the visitor back there on success, instead of always landing on
 * /.
 */
export async function proxy(request: NextRequest) {
  const { pathname } = request.nextUrl

  if (pathname === "/dashboard") {
    return NextResponse.redirect(new URL("/", request.url))
  }

  const context = resolveAppContext(request.headers.get("host"))
  const hasValidSession =
    context === "admin"
      ? await hasValidAdminSession(request)
      : request.cookies.has(TENANT_TOKEN_COOKIE_NAME)

  if (pathname === "/login") {
    if (hasValidSession) {
      return NextResponse.redirect(new URL("/", request.url))
    }

    return context === "tenant" ? await withTenantDefaultLocaleCookie(request) : NextResponse.next()
  }

  if (!hasValidSession) {
    const loginUrl = new URL("/login", request.url)
    loginUrl.searchParams.set(
      REDIRECT_PARAM,
      buildRedirectTarget(request.nextUrl.pathname, request.nextUrl.search)
    )
    return NextResponse.redirect(loginUrl)
  }

  return NextResponse.next()
}

/**
 * Asks the API whether the session cookie on this request is a valid
 * super-admin session, over the internal container network, with the
 * `Host` header set to the public api.<base> hostname so the API resolves
 * the admin context correctly. Fails closed on any error, non-200, or timeout.
 */
async function hasValidAdminSession(request: NextRequest): Promise<boolean> {
  const cookieHeader = request.headers.get("cookie")

  if (!cookieHeader) {
    return false
  }

  const incomingHost = request.headers.get("host") ?? "localhost"
  const apiUrl = new URL(apiBaseUrl(incomingHost, request.nextUrl.protocol))
  const backendInternalHost = process.env.BACKEND_INTERNAL_HOST ?? "backend"
  const backendInternalPort = Number(process.env.BACKEND_INTERNAL_PORT ?? 8000)

  try {
    const { status } = await sendBackendRequest({
      host: backendInternalHost,
      port: backendInternalPort,
      path: "/api/admin/me",
      method: "GET",
      tenantHost: apiUrl.host,
      headers: {
        Cookie: cookieHeader,
        Origin: `${request.nextUrl.protocol}//${incomingHost}`,
      },
    })

    return status === 200
  } catch {
    return false
  }
}

/**
 * KOZ-34: sets the `kozijnr_locale` cookie (lib/i18n/locale.ts) to the
 * current tenant's default locale, server-side, before the login page's
 * root layout ever renders — so the login screen shows in the tenant's
 * configured language by default (DoD) with no flash of the visitor's
 * previous/browser locale, the same anti-flash reasoning the root layout
 * already applies to reading that cookie in the first place (see
 * app/layout.tsx's doc comment).
 *
 * Every unauthenticated visit to a tenant's /login overwrites the cookie
 * unconditionally (never merged with an existing value) — the ticket's
 * scope is explicit that there is no per-visitor override that persists:
 * every new login (and every fresh look at the login screen) starts from
 * the tenant's default locale again. The login screen has no language
 * switcher of its own (KOZ-29 only ever added one to the logged-in
 * `AppShell`, in components/nav-user.tsx), so this is the only place that
 * decides the login screen's language.
 *
 * Fails open on any error/timeout/malformed response: the existing cookie
 * (or the root layout's own DEFAULT_LOCALE fallback) is left in place
 * rather than blocking navigation to /login on a backend hiccup.
 */
async function withTenantDefaultLocaleCookie(request: NextRequest): Promise<NextResponse> {
  const response = NextResponse.next()

  // Unlike hasValidAdminSession (which calls the API's own api.<base>
  // hostname and relies on the Origin header + TenantResolverListener's
  // RESERVED_API fallback to resolve the *admin* subdomain), this goes to
  // the API with the tenant's own Host header directly — TenantResolverListener
  // resolves a tenant straight from Host for any non-reserved subdomain, no
  // Origin dance needed.
  const incomingHost = request.headers.get("host") ?? "localhost"
  const backendInternalHost = process.env.BACKEND_INTERNAL_HOST ?? "backend"
  const backendInternalPort = Number(process.env.BACKEND_INTERNAL_PORT ?? 8000)

  try {
    const { status, body } = await sendBackendRequest({
      host: backendInternalHost,
      port: backendInternalPort,
      path: "/api/tenant-locale",
      method: "GET",
      tenantHost: incomingHost,
    })

    if (status !== 200) {
      return response
    }

    const parsed: unknown = JSON.parse(body)
    const defaultLocale =
      parsed !== null && typeof parsed === "object" && "defaultLocale" in parsed
        ? (parsed as { defaultLocale: unknown }).defaultLocale
        : undefined

    if (typeof defaultLocale === "string" && isSupportedLocale(defaultLocale)) {
      response.cookies.set(LOCALE_COOKIE_NAME, defaultLocale, { path: "/", sameSite: "lax" })
    }
  } catch {
    // Fail open — see doc comment above.
  }

  return response
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico).*)"],
}
