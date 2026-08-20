import { NextRequest, NextResponse } from "next/server"

import { sendBackendRequest } from "@/lib/backend-request"

/**
 * Server-side proxy for tenant-user logout (KOZ-13 -> backend's
 * POST /api/logout, KOZ-15) — the counterpart to app/api/login/route.ts,
 * see that file's docstring for why a same-origin server-to-server proxy
 * is used instead of calling the backend directly from the browser
 * (tenant Host header + no CORS).
 *
 * KOZ-13 rework: the token now lives in an HttpOnly cookie, so this proxy
 * has nothing to read from the client-side request other than the Cookie
 * header itself, which it forwards to the backend unchanged (the backend
 * reads the token from it via
 * App\TenantUser\Infrastructure\Security\CookieAccessTokenExtractor).
 * The backend's response, in turn, sets an already-expired Set-Cookie to
 * actually clear it — forwarded back to the browser the same way
 * app/api/login/route.ts forwards the one that sets it.
 *
 * KOZ-14 rework (round 4): now goes through the shared `sendBackendRequest`
 * helper (`lib/backend-request.ts`) too, so a hung backend here times out
 * and falls into the existing catch block below (still a 204, per the
 * best-effort reasoning already documented there) instead of leaving this
 * request hanging as well.
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
      path: "/api/logout",
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
    // Logging out is best-effort from this UI's perspective: the dashboard
    // placeholder always navigates back to /login regardless of the
    // outcome (see components handling this call), so a backend/network
    // failure here (including a timeout) has nothing more useful to
    // report than "done".
    return new NextResponse(null, { status: 204 })
  }
}
