import { NextRequest, NextResponse } from "next/server"

import { sendBackendRequest } from "@/lib/backend-request"

/**
 * Server-side proxy for super-admin logout (KOZ-14 rework, round 6 ->
 * backend's POST /api/admin/logout, AdminLogoutController) — the
 * admin-side counterpart to app/api/logout/route.ts, see that file's
 * docstring for the full "why a proxy, why node:http" background
 * (identical reasoning: Fetch can't set a Host header, and that's what
 * makes the backend resolve the admin firewall for this request).
 *
 * KOZ-8's `super_admin` firewall is session-based (PHPSESSID by default) —
 * this forwards the incoming Cookie header unchanged so the backend's
 * logout listener (LogoutSuccessHandler) can find and invalidate the
 * session, and forwards back whatever Set-Cookie header it responds with
 * (an already-expired cookie, clearing it in the browser) — same pattern
 * as the tenant logout proxy, just against the admin session instead of
 * the tenant bearer-token cookie.
 *
 * Round 6 fix: previously nav-user.tsx's handleLogout only called
 * /api/logout for the tenant context and just navigated to /login for
 * admin, without ever invalidating the PHPSESSID session — this route is
 * what closes that gap (see nav-user.tsx for the caller-side fix).
 */
export async function POST(request: NextRequest): Promise<NextResponse> {
  const incomingHost = request.headers.get("host") ?? "localhost"
  const hostname = incomingHost.split(":")[0]
  const backendInternalHost = process.env.BACKEND_INTERNAL_HOST ?? "backend"
  const backendInternalPort = Number(process.env.BACKEND_INTERNAL_PORT ?? 8000)
  const cookieHeader = request.headers.get("cookie") ?? ""

  try {
    const { headers } = await sendBackendRequest({
      host: backendInternalHost,
      port: backendInternalPort,
      path: "/api/admin/logout",
      method: "POST",
      tenantHost: hostname,
      headers: { Cookie: cookieHeader },
    })

    const response = new NextResponse(null, { status: 204 })
    const setCookieHeaders = headers["set-cookie"] ?? []
    for (const cookie of setCookieHeaders) {
      response.headers.append("set-cookie", cookie)
    }
    return response
  } catch {
    // Logging out is best-effort from this UI's perspective, same
    // reasoning as app/api/logout/route.ts: nav-user.tsx always navigates
    // back to /login regardless of the outcome, so a backend/network
    // failure here (including a timeout) has nothing more useful to
    // report than "done".
    return new NextResponse(null, { status: 204 })
  }
}
