import { NextRequest, NextResponse } from "next/server"

import { sendBackendRequest } from "@/lib/backend-request"

const GENERIC_ERROR = { message: "Invalid credentials." } as const

/**
 * Server-side proxy for the tenant-user login (KOZ-13 -> backend's
 * POST /api/login, KOZ-11).
 *
 * Why a proxy instead of calling the backend directly from the browser:
 * the backend resolves the tenant purely from the request's Host header
 * (App\Tenancy\Infrastructure\TenantResolverListener), so a login request
 * must be sent to the exact same hostname the frontend is currently being
 * viewed on (e.g. `acme.kozijnr.test`) — but that hostname is served on
 * the *frontend's* port (3000, or 3000+n per worktree) while the backend
 * listens on a different port (8000 / 8000+n). That makes it a genuine
 * cross-origin request, and the backend has no CORS headers configured
 * (deliberately out of this ticket's scope — see kozijnr-backend). Rather
 * than adding CORS to the backend, this route handler forwards the
 * request server-side: server-to-server calls aren't subject to
 * same-origin policy.
 *
 * Reaching the backend: the *published* host ports (e.g.
 * acme.kozijnr-koz-13.test:8013) only resolve from the host machine (via
 * Valet's dnsmasq) — not from inside this frontend container, which has
 * its own network namespace and loopback. Inside Docker's compose
 * network, the backend is always reachable at the stable internal address
 * `backend:8000` (the service name from docker-compose.yml;
 * container-internal port 8000 never changes per worktree, only the
 * published host port does). The *tenant* is still selected correctly by
 * forwarding the incoming request's own Host header (minus the frontend's
 * port) as the outgoing request's Host header — the backend only ever
 * looks at that header, never at how the connection was physically
 * routed.
 *
 * Uses Node's `http` module rather than `fetch`: the Fetch spec forbids
 * scripts from setting a `Host` header on a fetch request (it's a
 * "forbidden request-header"), silently dropping it — which is exactly
 * the header this proxy needs to control. `http.request` has no such
 * restriction.
 *
 * KOZ-13 rework — HttpOnly cookie instead of localStorage: the backend now
 * hands the token back as a `Set-Cookie` header
 * (App\TenantUser\Infrastructure\Security\TenantApiTokenCookie), not in
 * the JSON body — this route forwards that header verbatim onto the
 * response it sends the browser, so the browser (not this server, and
 * definitely not client-side JS) is the one that ends up holding the
 * cookie. Nothing here ever reads or stores the token value itself.
 *
 * KOZ-14 rework (round 4): the actual `node:http` call now goes through
 * the shared `sendBackendRequest` helper (`lib/backend-request.ts`),
 * extracted alongside `proxy.ts`'s `hasValidAdminSession` — same
 * boilerplate, now with a bounded timeout applied in one place instead of
 * being (previously: not being) duplicated per call site. A timeout here
 * surfaces as `sendBackendRequest` rejecting, which the existing catch
 * block below already turns into the same generic 401 as any other
 * backend failure.
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
      path: "/api/login",
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
