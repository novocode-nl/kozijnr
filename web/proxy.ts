import { request as httpRequest } from "node:http"
import { NextRequest, NextResponse } from "next/server"

import { TENANT_TOKEN_COOKIE_NAME } from "@/lib/auth/token-cookie"
import { resolveAppContext } from "@/lib/context/app-context"

/**
 * Server-side guard for every route (KOZ-13, KOZ-14, KOZ-14 rework round
 * 3). This file is Next.js 16's `proxy.ts` convention (formerly
 * `middleware.ts`, renamed upstream — see
 * node_modules/next/dist/docs/.../file-conventions/proxy.md) and runs
 * before the route renders. Next.js 16 defaults `proxy.ts` to the Node.js
 * runtime (not Edge), which is what makes the `node:http` call below
 * possible — same runtime the existing app/api/login and app/api/logout
 * route handlers already rely on for the same reason (see their
 * docstrings): Fetch forbids scripts from setting a `Host` header, and
 * that header is exactly what's needed to make the backend resolve the
 * right tenant/admin context.
 *
 * *** Rework history ***
 * KOZ-13: tenant bearer token moved from localStorage to an HttpOnly
 * cookie, so this guard only ever checks the cookie's *presence*, not its
 * validity (no round-trip to GET /api/me) — see the tenant branch below
 * for why that's still fine to leave as-is.
 *
 * KOZ-14 (round 2): `/dashboard` became the single path for both the
 * tenant and admin context, but only `/dashboard` itself was guarded, and
 * only for the tenant context — the admin context had no guard at all
 * (anyone could load admin.<domein>/dashboard's shell), because there was
 * no admin-facing frontend session to check yet.
 *
 * KOZ-14 (round 3, this rework): functional review's extra requirement —
 * *every* route under either subdomain must sit behind a login, not just
 * /dashboard, and the admin side must be exactly as strict as the tenant
 * side. Two changes from round 2:
 *
 * 1. The `matcher` below now covers every route (except Next.js's own
 *    static/image assets) instead of just "/dashboard" — see PUBLIC_PATHS
 *    for the small, explicit set of paths that must stay reachable
 *    without a session (the login screens themselves, and the two
 *    backend-proxying login/logout API routes; without carving these out
 *    a session-less visitor could never reach the login form or log in at
 *    all, and would get redirected in an infinite loop instead).
 * 2. The admin context is no longer waved through unconditionally. It now
 *    requires a valid KOZ-8 super-admin session, verified by asking the
 *    backend directly (GET /api/admin/me — see
 *    api/src/User/Infrastructure/Controller/AdminMeController.php, added
 *    in this rework as the admin-side counterpart to the tenant's
 *    existing GET /api/me) whether the session cookie the browser sent
 *    along is still valid. Unlike the tenant check, this one DOES need a
 *    real round-trip: the tenant check only inspects a cookie *this
 *    frontend itself* controls the shape of (it merely needs to exist),
 *    while the super-admin session is a Symfony session this frontend has
 *    no way to introspect locally at all (the cookie is just an opaque
 *    session id — PHPSESSID by default, no `framework.session.name`
 *    override in api/config/packages/framework.yaml) — the backend is the
 *    only thing that can say whether it's still valid. Note this is a
 *    *stricter* check than the tenant branch's "cookie merely present"
 *    one — deliberately: the ticket asks for "a valid token/session",
 *    and unlike the tenant cookie there was no existing, already-reviewed
 *    "presence is enough" behaviour to preserve here.
 *
 *    Redirect target for a missing/invalid admin session: `/admin/login`.
 *    There is still no real super-admin login *form* in this frontend —
 *    building one is explicitly out of this ticket's scope (KOZ-8's login
 *    is backend-only today, see app/admin/login/page.tsx's own docstring
 *    for the reasoning) — so this is a deliberate, documented placeholder
 *    redirect target, not the finished login screen. What matters for
 *    this ticket's DoD is that the *guard* is complete and unconditional:
 *    every admin route redirects somewhere when there's no valid session,
 *    and that somewhere renders instead of 404ing.
 */
const PUBLIC_PATHS = new Set(["/login", "/admin/login", "/api/login", "/api/logout"])

export async function proxy(request: NextRequest) {
  const { pathname } = request.nextUrl

  if (PUBLIC_PATHS.has(pathname)) {
    return NextResponse.next()
  }

  const context = resolveAppContext(request.headers.get("host"))

  if (context === "admin") {
    const hasValidSession = await hasValidAdminSession(request)

    if (!hasValidSession) {
      return NextResponse.redirect(new URL("/admin/login", request.url))
    }

    return NextResponse.next()
  }

  const hasToken = request.cookies.has(TENANT_TOKEN_COOKIE_NAME)

  if (!hasToken) {
    return NextResponse.redirect(new URL("/login", request.url))
  }

  return NextResponse.next()
}

/**
 * Asks the backend whether the session cookie on this request is a valid
 * KOZ-8 super-admin session, via GET /api/admin/me (added in this rework
 * — see api/src/User/Infrastructure/Controller/AdminMeController.php).
 * Mirrors app/api/login/route.ts's `proxyToBackend` helper exactly (same
 * internal-network reachability constraints, same reason for `node:http`
 * over `fetch` — see this file's top docstring): forwards the incoming
 * request's Host header (so the backend's tenant/admin resolution sees
 * the actual admin.<domein> host, not the internal `backend:8000`
 * address) and its Cookie header (whatever session cookie the browser
 * sent, forwarded byte-for-byte — this code never needs to know its name
 * or parse it) unchanged, and reports whether the backend answered 200.
 *
 * A request with no Cookie header at all is rejected immediately, without
 * a network round-trip: there is nothing for the backend to validate
 * either way, and this is the overwhelmingly common case (a
 * session-less visitor hitting the admin subdomain for the first time).
 */
function hasValidAdminSession(request: NextRequest): Promise<boolean> {
  const cookieHeader = request.headers.get("cookie")

  if (!cookieHeader) {
    return Promise.resolve(false)
  }

  const incomingHost = request.headers.get("host") ?? "localhost"
  const hostname = incomingHost.split(":")[0]
  const backendInternalHost = process.env.BACKEND_INTERNAL_HOST ?? "backend"
  const backendInternalPort = Number(process.env.BACKEND_INTERNAL_PORT ?? 8000)

  return new Promise((resolve) => {
    const req = httpRequest(
      {
        host: backendInternalHost,
        port: backendInternalPort,
        path: "/api/admin/me",
        method: "GET",
        headers: {
          Host: hostname,
          Cookie: cookieHeader,
        },
      },
      (res) => {
        res.resume()
        res.on("end", () => resolve(res.statusCode === 200))
      }
    )

    // Network/backend failure: fail closed, same spirit as the redirect
    // this feeds into — no valid session could be confirmed, so none is
    // assumed.
    req.on("error", () => resolve(false))
    req.end()
  })
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico).*)"],
}
