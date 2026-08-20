import { NextRequest, NextResponse } from "next/server"

import { TENANT_TOKEN_COOKIE_NAME } from "@/lib/auth/token-cookie"
import { resolveAppContext } from "@/lib/context/app-context"

/**
 * Server-side guard for /dashboard (KOZ-13, KOZ-14 rework). This file is
 * Next.js 16's `proxy.ts` convention (formerly `middleware.ts`, renamed
 * upstream — see node_modules/next/dist/docs/.../file-conventions/proxy.md)
 * and runs before the route renders.
 *
 * The bearer token now lives in an HttpOnly cookie the browser can't read,
 * so the previous client-side "is there a token in localStorage" check
 * (app/dashboard/page.tsx, before this rework) no longer has anything to
 * check — the whole point of the HttpOnly cookie is that client-side JS
 * has no visibility into it at all.
 *
 * This only checks that the cookie is *present*, not that it's still
 * valid (no round-trip to GET /api/me) — a deliberately pragmatic choice
 * matching /dashboard's own status as a placeholder (the real dashboard
 * hasn't shipped yet): an expired/revoked-but-present cookie still lets
 * the placeholder render, and any actual protected data fetch would 401
 * and can be handled then, once there is real data to fetch.
 *
 * KOZ-14 rework: `/dashboard` is now the single path for both the tenant
 * and the admin context (see lib/context/app-context.ts) — the old
 * `/admin` route is gone, the Host subdomain alone decides which menu and
 * content render. The tenant HttpOnly cookie this guard checks is
 * meaningless for the admin subdomain, though: there is still no
 * admin-facing frontend login/session at all (same "deliberately
 * provisional" gap app-context.ts already documents), so requiring it
 * there would just lock everyone out of admin.<domein>/dashboard with no
 * way to obtain that cookie. This guard therefore only enforces the
 * tenant cookie check for the tenant context, and — matching the "route
 * isn't guarded yet" status quo the admin placeholder already had —
 * lets the admin context through unguarded until a real admin session
 * exists to check instead.
 */
export function proxy(request: NextRequest) {
  const context = resolveAppContext(request.headers.get("host"))

  if (context === "admin") {
    return NextResponse.next()
  }

  const hasToken = request.cookies.has(TENANT_TOKEN_COOKIE_NAME)

  if (!hasToken) {
    return NextResponse.redirect(new URL("/login", request.url))
  }

  return NextResponse.next()
}

export const config = {
  matcher: ["/dashboard"],
}
