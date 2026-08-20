import { NextRequest, NextResponse } from "next/server"

import { sendBackendRequest } from "@/lib/backend-request"
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
 *    Redirect target for a missing/invalid admin session was `/admin/login`
 *    at the time — a deliberate placeholder page, since there was no real
 *    super-admin login *form* in this frontend yet. See round 5 below:
 *    that placeholder and its separate path are gone, superseded by the
 *    unified `/login`.
 *
 * KOZ-14 (round 4) — automated code review flagged that the round-3
 * GET /api/admin/me round-trip had no timeout: if the backend accepted the
 * connection but never responded (hang, deadlock, a slow query),
 * `hasValidAdminSession` would await forever, blocking *every* admin
 * route — not just a login attempt, but also already-logged-in admins —
 * for as long as the backend stayed unresponsive. Fixed by routing the
 * request through the new shared `sendBackendRequest` helper
 * (`lib/backend-request.ts`, also now used by app/api/login/route.ts),
 * which applies a bounded timeout via `req.setTimeout` and rejects instead
 * of hanging. A rejection here (timeout or any other network failure) is
 * treated the same as "no valid session" — fail closed, but within a
 * short, predictable time instead of indefinitely.
 *
 * KOZ-14 (round 5, this rework) — functional review's two remaining
 * points:
 *
 * 1. `/login` is now a *single, unified* path for both contexts (see
 *    app/login/page.tsx, which picks the tenant or admin form based on
 *    the same Host-derived context this guard uses). The separate
 *    `/admin/login` placeholder page is gone, so both branches below now
 *    redirect an unauthenticated request to the same `/login` — there is
 *    no more context-specific redirect target to keep in sync.
 * 2. An already-authenticated visitor who navigates to `/login` is sent
 *    straight to `/dashboard` instead of being shown the form again — the
 *    session validity this guard already computes for every other route
 *    is reused for `/login` itself rather than treating it as an
 *    unconditionally public path. Only the backend-proxying API routes in
 *    PUBLIC_API_PATHS stay unconditionally public: a session-less visitor
 *    must still be able to reach them to log in in the first place
 *    (carving `/login` out entirely, as before, would otherwise make the
 *    redirect loop back on itself once `/login` also checks the session).
 *
 * KOZ-14 (round 6, this rework) — code review flagged that
 * nav-user.tsx's handleLogout never called a backend logout endpoint for
 * the admin context (it only did for tenant), so an admin's PHPSESSID
 * session stayed valid after clicking "Uitloggen" — combined with round
 * 5's "already-valid-session -> /dashboard" redirect above, that made the
 * logout button visibly do nothing for admins. Fixed on the caller side
 * (nav-user.tsx now calls the new app/api/admin/logout/route.ts for the
 * admin context too) plus here: `/api/admin/logout` added to
 * PUBLIC_API_PATHS so that proxy call itself isn't blocked by this same
 * guard.
 */
const PUBLIC_API_PATHS = new Set([
  "/api/login",
  "/api/logout",
  "/api/admin/login",
  "/api/admin/logout",
])

export async function proxy(request: NextRequest) {
  const { pathname } = request.nextUrl

  if (PUBLIC_API_PATHS.has(pathname)) {
    return NextResponse.next()
  }

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
 * Asks the backend whether the session cookie on this request is a valid
 * KOZ-8 super-admin session, via GET /api/admin/me (added in this rework
 * — see api/src/User/Infrastructure/Controller/AdminMeController.php),
 * through the shared `sendBackendRequest` helper (`lib/backend-request.ts`
 * — same helper `app/api/login/route.ts` uses, see that file's docstring
 * for the full "why node:http, why the internal host/port" background).
 * Forwards the incoming request's Host header (so the backend's
 * tenant/admin resolution sees the actual admin.<domein> host, not the
 * internal `backend:8000` address) and its Cookie header (whatever
 * session cookie the browser sent, forwarded byte-for-byte — this code
 * never needs to know its name or parse it) unchanged, and reports
 * whether the backend answered 200.
 *
 * A request with no Cookie header at all is rejected immediately, without
 * a network round-trip: there is nothing for the backend to validate
 * either way, and this is the overwhelmingly common case (a
 * session-less visitor hitting the admin subdomain for the first time).
 *
 * Fail-closed on any failure to confirm a valid session — a network
 * error, a non-200 response, *and* (round 4 fix) a request that times out
 * because the backend never responded — all resolve `false`, never leave
 * the caller hanging. See `sendBackendRequest`'s docstring for the
 * timeout mechanics.
 */
async function hasValidAdminSession(request: NextRequest): Promise<boolean> {
  const cookieHeader = request.headers.get("cookie")

  if (!cookieHeader) {
    return false
  }

  const incomingHost = request.headers.get("host") ?? "localhost"
  const hostname = incomingHost.split(":")[0]
  const backendInternalHost = process.env.BACKEND_INTERNAL_HOST ?? "backend"
  const backendInternalPort = Number(process.env.BACKEND_INTERNAL_PORT ?? 8000)

  try {
    const { status } = await sendBackendRequest({
      host: backendInternalHost,
      port: backendInternalPort,
      path: "/api/admin/me",
      method: "GET",
      tenantHost: hostname,
      headers: { Cookie: cookieHeader },
    })

    return status === 200
  } catch {
    // Network/backend failure (including a timed-out, hung backend): fail
    // closed, same spirit as the redirect this feeds into — no valid
    // session could be confirmed, so none is assumed.
    return false
  }
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico).*)"],
}
