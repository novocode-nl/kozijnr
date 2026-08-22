import { type IncomingHttpHeaders, request as httpRequest } from "node:http"

/**
 * Server-to-server request helper for proxy.ts's admin session check. Uses
 * `node:http` instead of `fetch` because it needs to set the `Host` header
 * explicitly (Fetch forbids that). Applies a bounded timeout so a backend
 * that accepts the connection but never responds doesn't hang the guard.
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
 * Sends a single request to the backend, resolving once the response body
 * has fully drained. Rejects (never hangs) on a network error or on
 * exceeding `timeoutMs` — callers needing fail-closed behaviour should
 * treat a rejection as an explicit denial.
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

    // Destroying on timeout routes through the same "error" handler below,
    // giving a single exit path for both a timeout and a network error.
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
