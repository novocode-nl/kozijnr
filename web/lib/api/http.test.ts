import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"

import { apiErrorMessage, backendUrl, getJsonOrThrow, requestAction } from "./http"

beforeEach(() => {
  vi.stubGlobal("window", { location: { host: "admin.kozijnr.localhost", protocol: "http:" } })
})
afterEach(() => {
  vi.unstubAllGlobals()
})

const jsonResponse = (status: number, body: unknown) =>
  new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } })

describe("backendUrl", () => {
  it("targets api.<base> derived from the current host", () => {
    expect(backendUrl("/api/me")).toBe("http://api.kozijnr.localhost/api/me")
  })
})

describe("apiErrorMessage", () => {
  it("translates a known errorKey", () => {
    // tenants.error.createFailed exists in both catalogs (used by lib/api today).
    expect(apiErrorMessage({ errorKey: "tenants.error.createFailed" }, "fallback")).not.toBe("fallback")
  })
  it("falls back to the body message for an unknown key", () => {
    expect(apiErrorMessage({ errorKey: "nope.error.unknown", message: "Boom" }, "fallback")).toBe("Boom")
  })
  it("falls back to the caller fallback without a body", () => {
    expect(apiErrorMessage(null, "fallback")).toBe("fallback")
  })
})

describe("requestAction", () => {
  it("returns success with parsed data", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(jsonResponse(201, { email: "a@b.nl" })))
    const result = await requestAction<{ email: string }>("/api/x", { method: "POST", json: { a: 1 }, fallbackMessage: () => "failed" })
    expect(result).toEqual({ success: true, data: { email: "a@b.nl" } })
    expect(vi.mocked(fetch).mock.calls[0][1]).toMatchObject({ credentials: "include", method: "POST" })
  })

  it("maps a non-OK response onto the configured errorField", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(jsonResponse(422, { message: "Bad email" })))
    const result = await requestAction("/api/x", { method: "POST", json: {}, fallbackMessage: () => "failed", errorField: "email" })
    expect(result).toEqual({ success: false, fieldErrors: { email: "Bad email" } })
  })

  it("returns a plain message on a network error, even with errorField set", async () => {
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new TypeError("offline")))
    const result = await requestAction("/api/x", { method: "POST", json: {}, fallbackMessage: () => "failed", errorField: "email" })
    expect(result).toEqual({ success: false, message: "failed" })
  })
})

describe("getJsonOrThrow", () => {
  it("throws with the status on a non-OK response", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response("", { status: 500 })))
    await expect(getJsonOrThrow("/api/x", "tenants")).rejects.toThrow("Failed to load tenants (status 500).")
  })
})
