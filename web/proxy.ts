import { NextRequest, NextResponse } from "next/server"

import { apiBaseUrl } from "@/lib/api-base-url"
import { sendBackendRequest } from "@/lib/backend-request"
import { TENANT_TOKEN_COOKIE_NAME } from "@/lib/auth/token-cookie"
import { resolveAppContext } from "@/lib/context/app-context"
import { REDIRECT_PARAM, buildRedirectTarget } from "@/lib/navigation/safe-redirect"

/**
 * Server-side route guard (Next.js 16 `proxy.ts` convention, formerly
 * `middleware.ts`; runs on the Node.js runtime, which is what makes the
 * `node:http` call in `hasValidAdminSession` possible).
 *
 * Every route on either subdomain sits behind a login:
 * - tenant context (<tenant>.<base>): the HttpOnly token cookie the API
 *   sets on login merely has to be present — its validity is checked by
 *   the API on every real call anyway.
 * - admin context (admin.<base>): the super-admin session is an opaque
 *   Symfony session cookie this frontend can't introspect, so the API is
 *   asked (GET /api/admin/me) whether it's still valid. Fails closed on any
 *   error or timeout.
 *
 * `/login` is the single login path for both contexts; an already
 * authenticated visitor is sent on to /dashboard. There are no API routes
 * in this frontend — the browser talks to api.<base> directly (see
 * lib/api.ts), so nothing under /api needs carving out here.
 *
 * KOZ-20: a bounce to `/login` carries the originally requested page as
 * `?redirect=<path>` (lib/navigation/safe-redirect.ts) so the login form
 * can send the visitor back there on success, instead of always landing on
 * /dashboard. Navigating to `/login` directly (not via this guard) carries
 * no such param, so that case's behavior is unchanged.
 */
export async function proxy(request: NextRequest) {
  const { pathname } = request.nextUrl

  const context = resolveAppContext(request.headers.get("host"))
  const hasValidSession =
    context === "admin"
      ? await hasValidAdminSession(request)
      : request.cookies.has(TENANT_TOKEN_COOKIE_NAME)

  if (pathname === "/login") {
    return hasValidSession
      ? NextResponse.redirect(new URL("/dashboard", request.url))
      : NextResponse.next()
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
 * super-admin session (GET /api/admin/me) the same way the browser would:
 * addressed to api.<base> with the page's origin as `Origin`, so the API's
 * TenantResolverListener resolves the admin context from it. Goes over the
 * internal container network (`backend:8000`), setting the `Host` header to
 * the public api.<base> hostname.
 *
 * No Cookie header at all -> false without a round-trip. Any network
 * failure, non-200 or timeout -> false (fail closed).
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

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico).*)"],
}
