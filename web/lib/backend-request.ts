import { type IncomingHttpHeaders, request as httpRequest } from "node:http"

/**
 * Server-to-server request helper for the one place this frontend talks to
 * the backend from its own server side: the route guard in `proxy.ts`
 * (admin session check). Uses `node:http` instead of `fetch` because it
 * needs to set the `Host` header explicitly (Fetch forbids that), and
 * targets the backend's internal container address (`backend:8000`) since
 * the public api.<base> hostname only resolves on the developer's machine,
 * not inside the container network.
 *
 * Applies a bounded timeout: if the backend accepts the TCP connection but
 * never responds, the request rejects instead of hanging — important
 * because the guard sits on every admin route.
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
