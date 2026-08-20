import { NextRequest, NextResponse } from "next/server"

import { sendBackendRequest } from "@/lib/backend-request"

const GENERIC_ERROR = { message: "Invalid credentials." } as const

/**
 * Server-side proxy for the super-admin login (KOZ-14 rework, round 5 ->
 * backend's POST /api/admin/login, KOZ-8) — the admin-side counterpart to
 * app/api/login/route.ts, see that file's docstring for the full "why a
 * proxy, why node:http, why the internal host/port" background (identical
 * reasoning here: the backend resolves admin-vs-tenant purely from the
 * request's Host header, and the frontend/backend live on different ports,
 * so this is a genuine cross-origin request without CORS configured).
 *
 * KOZ-8's `super_admin` firewall is session-based (not the tenant's
 * stateless bearer-token cookie) — a successful POST /api/admin/login sets
 * a `Set-Cookie` for the Symfony session (PHPSESSID by default, see
 * config/packages/framework.yaml) via
 * App\User\Infrastructure\Security\AuthenticationSuccessHandler. Exactly
 * like the tenant login proxy, this route forwards that Set-Cookie header
 * verbatim onto the response it sends the browser — the browser ends up
 * holding the session cookie, this server never inspects or stores it.
 *
 * Previously there was no frontend login form driving this endpoint at
 * all (see the now-removed app/admin/login/page.tsx placeholder's
 * docstring, superseded by the unified /login page +
 * components/admin-login-form.tsx in this rework) — functional review
 * corrected the earlier "separate ticket" call, this belongs to KOZ-14.
 */
export async function POST(request: NextRequest): Promise<NextResponse> {
  const incomingHost = request.headers.get("host") ?? "localhost"
  const hostname = incomingHost.split(":")[0]
  const backendInternalHost = process.env.BACKEND_INTERNAL_HOST ?? "backend"
  const backendInternalPort = Number(process.env.BACKEND_INTERNAL_PORT ?? 8000)

  let bodyText: string
  try {
    const body = await request.json()
    bodyText = JSON.stringify(body)
  } catch {
    return NextResponse.json(GENERIC_ERROR, { status: 401 })
  }

  try {
    const { status, headers } = await sendBackendRequest({
      host: backendInternalHost,
      port: backendInternalPort,
      path: "/api/admin/login",
      method: "POST",
      tenantHost: hostname,
      headers: {
        "Content-Type": "application/json",
        "Content-Length": String(Buffer.byteLength(bodyText)),
      },
      body: bodyText,
    })

    if (status !== 200) {
      return NextResponse.json(GENERIC_ERROR, { status: 401 })
    }

    const response = NextResponse.json({ success: true }, { status: 200 })
    const setCookieHeaders = headers["set-cookie"] ?? []
    for (const cookie of setCookieHeaders) {
      response.headers.append("set-cookie", cookie)
    }
    return response
  } catch {
    return NextResponse.json(GENERIC_ERROR, { status: 401 })
  }
}
