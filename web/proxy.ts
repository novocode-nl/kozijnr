import { NextRequest, NextResponse } from "next/server"

import { apiBaseUrl } from "@/lib/api-base-url"
import { sendBackendRequest } from "@/lib/backend-request"
import { TENANT_TOKEN_COOKIE_NAME } from "@/lib/auth/token-cookie"
import { resolveAppContext } from "@/lib/context/app-context"

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
 * authenticated visitor is sent on to /. There are no API routes in this
 * frontend — the browser talks to api.<base> directly (see lib/api.ts), so
 * nothing under /api needs carving out here.
 *
 * `/dashboard` (KOZ-21): no longer exists as its own route on either
 * subdomain — it always redirects to / before the login-status check below,
 * so it behaves the same whether the visitor is logged in or not, and
 * regardless of admin vs. tenant context.
 *
 * 404 handling (KOZ-21): the login-status check below already covers
 * "unknown route, not logged in" — since every path other than /login
 * redirects to /login when there's no valid session, a nonexistent route
 * bounces to /login exactly like a real one would, without ever reaching
 * Next's routing/notFound() at all. Order matters here: login status is
 * resolved first, and only a session-holding visitor ever reaches Next's
 * route resolution (where app/(app)/not-found.tsx renders the branded 404
 * for a route that truly doesn't exist).
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
    return hasValidSession
      ? NextResponse.redirect(new URL("/", request.url))
      : NextResponse.next()
  }

  if (!hasValidSession) {
    return NextResponse.redirect(new URL("/login", request.url))
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
