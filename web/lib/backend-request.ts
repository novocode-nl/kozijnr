import { type IncomingHttpHeaders, request as httpRequest } from "node:http"

/**
 * Shared server-to-server request helper for talking to the backend from
 * this frontend (KOZ-14 rework, round 4). Extracted out of
 * `app/api/login/route.ts`'s `proxyToBackend` and `proxy.ts`'s
 * `hasValidAdminSession` — both were near-identical `node:http` boilerplate
 * (same reason for `node:http` over `fetch`, same internal-network
 * reachability constraints; see either call site's docstring for the full
 * "why http.request, why the internal host/port" background, not repeated
 * here).
 *
 * The concrete bug this consolidation fixes: neither call site had a
 * timeout on the request. If the backend accepts the TCP connection but
 * never responds (hang, deadlock, a slow query holding a DB connection),
 * the request — and therefore every caller awaiting it — would hang
 * indefinitely. For `hasValidAdminSession` in particular, that request sits
 * on the guard for *every* admin route, so a hung backend would hang the
 * entire admin surface, not just a login attempt. Fixing it once here
 * means both call sites get the fix, instead of needing the same
 * `req.setTimeout` boilerplate duplicated a second time.
 */
const DEFAULT_TIMEOUT_MS = 5000

export interface BackendRequestOptions {
  host: string
  port: number
  path: string
  method: "GET" | "POST"
  /** Forwarded as the outgoing request's `Host` header. */
  tenantHost: string
  headers?: Record<string, string>
  body?: string
  /** Defaults to {@link DEFAULT_TIMEOUT_MS}. */
  timeoutMs?: number
}

export interface BackendResponse {
  status: number
  headers: IncomingHttpHeaders
}

/**
 * Sends a single request to the backend and resolves with its status and
 * headers once the response body has fully drained.
 *
 * Rejects (never hangs) on:
 * - a network-level error (connection refused, DNS failure, etc.)
 * - the request exceeding `timeoutMs` without a response — the socket is
 *   destroyed and the promise rejects, so a hung/slow backend fails fast
 *   and predictably instead of blocking the caller forever.
 *
 * Callers that need fail-closed behaviour (e.g. an auth guard) should
 * catch the rejection and treat it the same as an explicit denial — see
 * `proxy.ts`'s `hasValidAdminSession`.
 */
export function sendBackendRequest(options: BackendRequestOptions): Promise<BackendResponse> {
  const { host, port, path, method, tenantHost, headers, body, timeoutMs = DEFAULT_TIMEOUT_MS } = options

  return new Promise((resolve, reject) => {
    const req = httpRequest(
      {
        host,
        port,
        path,
        method,
        headers: {
          Host: tenantHost,
          ...headers,
        },
      },
      (res) => {
        res.resume()
        res.on("end", () => {
          resolve({ status: res.statusCode ?? 500, headers: res.headers })
        })
      }
    )

    // Fires if no data (including the response headers) is exchanged on
    // the socket within timeoutMs — this is what catches a backend that
    // accepted the connection but then never answers. Destroying the
    // socket triggers the "error" handler below, so there's a single exit
    // path for both a timeout and a genuine network error.
    req.setTimeout(timeoutMs, () => {
      req.destroy(new Error(`Backend request to ${path} timed out after ${timeoutMs}ms`))
    })

    req.on("error", reject)

    if (body !== undefined) {
      req.write(body)
    }
    req.end()
  })
}
