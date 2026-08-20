import { NextRequest, NextResponse } from "next/server"

import { TENANT_TOKEN_COOKIE_NAME } from "@/lib/auth/token-cookie"

/**
 * Server-side guard for /dashboard (KOZ-13 rework). This file is Next.js
 * 16's `proxy.ts` convention (formerly `middleware.ts`, renamed upstream —
 * see node_modules/next/dist/docs/.../file-conventions/proxy.md) and runs
 * before the route renders.
 *
 * The bearer token now lives in an HttpOnly cookie the browser can't read,
 * so the previous client-side "is there a token in localStorage" check
 * (app/dashboard/page.tsx, before this rework) no longer has anything to
 * check — the whole point of the HttpOnly cookie is that client-side JS
 * has no visibility into it at all.
 *
 * This only checks that the cookie is *present*, not that it's still
 * valid (no round-trip to GET /api/me) — a deliberately pragmatic choice
 * matching /dashboard's own status as a placeholder (KOZ-14 hasn't
 * shipped the real dashboard yet): an expired/revoked-but-present cookie
 * still lets the placeholder render, and any actual protected data fetch
 * would 401 and can be handled then, once there is real data to fetch.
 */
export function proxy(request: NextRequest) {
  const hasToken = request.cookies.has(TENANT_TOKEN_COOKIE_NAME)

  if (!hasToken) {
    return NextResponse.redirect(new URL("/login", request.url))
  }

  return NextResponse.next()
}

export const config = {
  matcher: ["/dashboard"],
}
