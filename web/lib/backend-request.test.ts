import { createServer, type Server, type Socket } from "node:net"

import { afterEach, describe, expect, it } from "vitest"

import { sendBackendRequest } from "@/lib/backend-request"

/**
 * Regression coverage: `hasValidAdminSession` (proxy.ts) had no timeout on
 * its backend round-trip, so a backend that accepted the connection but
 * never responded would hang the caller forever. Exercises
 * `sendBackendRequest` against a raw TCP server under our control as a
 * stand-in for that stuck-backend scenario.
 */
describe("sendBackendRequest", () => {
  let server: Server | undefined
  let openSockets: Socket[] = []

  afterEach(async () => {
    // The stub server never closes its own socket end, so server.close()
    // alone would wait forever — force-close sockets first.
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
      // Accept the connection but never write anything back.
      openSockets.push(socket)
      socket.on("error", () => {
        // Ignore expected ECONNRESET once the client times out.
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

    // Generous upper bound above the 100ms timeout, to tolerate CI jitter.
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
