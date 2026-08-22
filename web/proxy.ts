import { NextRequest, NextResponse } from "next/server"

import { apiBaseUrl } from "@/lib/api-base-url"
import { sendBackendRequest } from "@/lib/backend-request"
import { TENANT_TOKEN_COOKIE_NAME } from "@/lib/auth/token-cookie"
import { resolveAppContext } from "@/lib/context/app-context"
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
    return hasValidSession
      ? NextResponse.redirect(new URL("/", request.url))
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

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico).*)"],
}
