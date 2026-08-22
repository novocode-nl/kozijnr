import { NextRequest, NextResponse } from "next/server"

import { apiBaseUrl } from "@/lib/api-base-url"
import { sendBackendRequest } from "@/lib/backend-request"
import { TENANT_TOKEN_COOKIE_NAME } from "@/lib/auth/token-cookie"
import { resolveAppContext } from "@/lib/context/app-context"

/**
 * Server-side route guard (Next.js `proxy.ts` convention; runs on the
 * Node.js runtime, needed for the `node:http` call in `hasValidAdminSession`).
 *
 * Every route on either subdomain sits behind a login: the tenant token
 * cookie only needs to be present (its validity is checked by the API on
 * every real call), but the admin session is an opaque Symfony session
 * cookie this frontend can't introspect, so the API is asked directly and
 * this fails closed on any error or timeout.
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
    return NextResponse.redirect(new URL("/login", request.url))
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
