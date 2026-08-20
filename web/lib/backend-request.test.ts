import { createServer, type Server, type Socket } from "node:net"

import { afterEach, describe, expect, it } from "vitest"

import { sendBackendRequest } from "@/lib/backend-request"

/**
 * KOZ-14 rework (round 4): regression coverage for the blocking finding
 * from automated code review — `hasValidAdminSession` (proxy.ts) had no
 * timeout on its backend round-trip, so a backend that accepted the
 * connection but never responded would hang the caller (and, through it,
 * every admin route) forever.
 *
 * These tests exercise `sendBackendRequest` directly against a raw TCP
 * server under our control, rather than trying to make the real backend
 * hang — that's the practical boundary for what's testable here without
 * standing up a full Next.js proxy test harness (there wasn't one before
 * this ticket; see this file's sibling `vitest.config.ts` for that
 * context). A server that accepts the connection and then writes nothing
 * back is a faithful stand-in for "backend accepted the TCP connection but
 * is stuck" — exactly the scenario the review flagged.
 */
describe("sendBackendRequest", () => {
  let server: Server | undefined
  let openSockets: Socket[] = []

  afterEach(async () => {
    // The client destroys its end of the socket on timeout, but this
    // "hung backend" stub deliberately never closes its own end, so
    // server.close() alone would wait forever for it. Force-close any
    // sockets the stub server accepted before closing the server itself.
    for (const socket of openSockets) {
      socket.destroy()
    }
    openSockets = []

    if (server) {
      await new Promise<void>((resolve) => server?.close(() => resolve()))
      server = undefined
    }
  })

  it("rejects instead of hanging forever when the backend never responds", async () => {
    server = createServer((socket) => {
      // Accept the TCP connection but never write anything back — the
      // "hung backend" scenario from the review finding.
      openSockets.push(socket)
      socket.on("error", () => {
        // Ignore ECONNRESET once the client destroys the socket on
        // timeout; it's expected and not part of what this test asserts.
      })
    })
    const port = await listen(server)

    const start = Date.now()

    await expect(
      sendBackendRequest({
        host: "127.0.0.1",
        port,
        path: "/api/admin/me",
        method: "GET",
        tenantHost: "admin.localhost",
        timeoutMs: 100,
      })
    ).rejects.toThrow(/timed out/)

    const elapsedMs = Date.now() - start

    // Generous upper bound (well above the 100ms timeout) to keep this
    // robust under CI/container scheduling jitter, while still proving the
    // call did not hang indefinitely (the old code had no upper bound at
    // all).
    expect(elapsedMs).toBeLessThan(2000)
  })

  it("still resolves normally when the backend responds promptly", async () => {
    server = createServer((socket) => {
      socket.on("data", () => {
        socket.end("HTTP/1.1 200 OK\r\nContent-Length: 0\r\nConnection: close\r\n\r\n")
      })
    })
    const port = await listen(server)

    const response = await sendBackendRequest({
      host: "127.0.0.1",
      port,
      path: "/api/admin/me",
      method: "GET",
      tenantHost: "admin.localhost",
      timeoutMs: 2000,
    })

    expect(response.status).toBe(200)
  })
})

function listen(server: Server): Promise<number> {
  return new Promise((resolve) => {
    server.listen(0, "127.0.0.1", () => {
      const address = server.address()
      if (address === null || typeof address === "string") {
        throw new Error("Expected server to listen on a TCP port")
      }
      resolve(address.port)
    })
  })
}
