import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"

import * as api from "@/lib/api"

/**
 * Characterization tests written against the pre-refactor lib/api.ts: they
 * pin down every endpoint wrapper's URL, method, body, credentials mode and
 * error mapping, and stay unchanged through the requestAction refactor as
 * the regression net for behavior that previously had no tests at all.
 */
beforeEach(() => {
  vi.stubGlobal("window", { location: { host: "admin.kozijnr.localhost", protocol: "http:" } })
})
afterEach(() => {
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

const jsonResponse = (status: number, body: unknown) =>
  new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } })

const file = () => new File(["x"], "x.png", { type: "image/png" })

type ActionCase = {
  name: string
  call: () => Promise<unknown>
  url: string
  method: "POST" | "PATCH"
  json?: unknown
  formDataField?: string
  /** Waar een 422-message landt: een fieldErrors-sleutel, of "message". */
  errorTarget: string
}

// Elke ActionResult-wrapper uit lib/api — deze tabel bevat ze allemaal.
const actionCases: ActionCase[] = [
  { name: "createTenantUser", call: () => api.createTenantUser("acme", { email: "a@b.nl", role: "ROLE_TENANT_USER" }), url: "http://api.kozijnr.localhost/api/admin/tenants/acme/users", method: "POST", json: { email: "a@b.nl", role: "ROLE_TENANT_USER" }, errorTarget: "email" },
  { name: "createOwnTenantUser", call: () => api.createOwnTenantUser({ email: "a@b.nl", role: "ROLE_TENANT_USER" }), url: "http://api.kozijnr.localhost/api/users", method: "POST", json: { email: "a@b.nl", role: "ROLE_TENANT_USER" }, errorTarget: "email" },
  { name: "createAdminUser", call: () => api.createAdminUser({ email: "a@b.nl" }), url: "http://api.kozijnr.localhost/api/admin/users", method: "POST", json: { email: "a@b.nl" }, errorTarget: "email" },
  { name: "createTenant", call: () => api.createTenant({ name: "Acme", slug: "acme", adminEmail: "a@b.nl" }), url: "http://api.kozijnr.localhost/api/admin/tenants", method: "POST", json: { name: "Acme", slug: "acme", adminEmail: "a@b.nl" }, errorTarget: "slug" },
  { name: "updateTenant", call: () => api.updateTenant("acme", { name: "Acme 2", slug: "acme-2" }), url: "http://api.kozijnr.localhost/api/admin/tenants/acme", method: "PATCH", json: { name: "Acme 2", slug: "acme-2" }, errorTarget: "slug" },
  { name: "archiveTenant", call: () => api.archiveTenant("acme"), url: "http://api.kozijnr.localhost/api/admin/tenants/acme/archive", method: "POST", errorTarget: "message" },
  { name: "unarchiveTenant", call: () => api.unarchiveTenant("acme"), url: "http://api.kozijnr.localhost/api/admin/tenants/acme/unarchive", method: "POST", errorTarget: "message" },
  { name: "updateTenantDefaultLocale", call: () => api.updateTenantDefaultLocale({ defaultLocale: "en" }), url: "http://api.kozijnr.localhost/api/settings/locale", method: "PATCH", json: { defaultLocale: "en" }, errorTarget: "defaultLocale" },
  { name: "uploadTenantLoginImage", call: () => api.uploadTenantLoginImage(file()), url: "http://api.kozijnr.localhost/api/settings/login-image", method: "POST", formDataField: "image", errorTarget: "loginImage" },
  { name: "uploadProfilePhoto", call: () => api.uploadProfilePhoto(file()), url: "http://api.kozijnr.localhost/api/me/profile-photo", method: "POST", formDataField: "photo", errorTarget: "message" },
]

describe.each(actionCases)("$name", ({ call, url, method, json, formDataField, errorTarget }) => {
  it("hits the right endpoint with credentials and returns the parsed data", async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(200, { ok: true }))
    vi.stubGlobal("fetch", fetchMock)

    const result = await call()

    const [calledUrl, init] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(calledUrl).toBe(url)
    expect(init).toMatchObject({ method, credentials: "include" })
    if (json !== undefined) {
      expect(init.headers).toMatchObject({ "Content-Type": "application/json" })
      expect(JSON.parse(init.body as string)).toEqual(json)
    }
    if (formDataField) {
      expect(init.body).toBeInstanceOf(FormData)
      expect((init.body as FormData).get(formDataField)).toBeInstanceOf(File)
    }
    expect(result).toEqual({ success: true, data: { ok: true } })
  })

  it("maps a non-OK response onto the right target", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(jsonResponse(422, { message: "Nope" })))

    const result = (await call()) as { success: false; message?: string; fieldErrors?: Record<string, string> }

    expect(result.success).toBe(false)
    if (errorTarget === "message") expect(result.message).toBe("Nope")
    else expect(result.fieldErrors).toEqual({ [errorTarget]: "Nope" })
  })

  it("returns a plain message on a network error", async () => {
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new TypeError("offline")))

    const result = (await call()) as { success: false; message?: string; fieldErrors?: unknown }

    expect(result.success).toBe(false)
    expect(typeof result.message).toBe("string")
    expect(result.fieldErrors).toBeUndefined()
  })
})

type GetCase = { name: string; call: () => Promise<unknown>; url: string }

// Elke throw-on-!ok GET-wrapper — ook deze tabel is volledig.
const getCases: GetCase[] = [
  { name: "listTenants", call: () => api.listTenants(), url: "http://api.kozijnr.localhost/api/admin/tenants" },
  { name: "listTenants archived", call: () => api.listTenants(true), url: "http://api.kozijnr.localhost/api/admin/tenants?archived=true" },
  { name: "listTenantUsers", call: () => api.listTenantUsers("acme"), url: "http://api.kozijnr.localhost/api/admin/tenants/acme/users" },
  { name: "listAdminUsers", call: () => api.listAdminUsers(), url: "http://api.kozijnr.localhost/api/admin/users" },
  { name: "listOwnTenantUsers", call: () => api.listOwnTenantUsers(), url: "http://api.kozijnr.localhost/api/users" },
  { name: "getTenantSettings", call: () => api.getTenantSettings(), url: "http://api.kozijnr.localhost/api/settings" },
]

describe.each(getCases)("$name", ({ call, url }) => {
  it("GETs with credentials and returns the parsed body", async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(200, [{ id: 1 }]))
    vi.stubGlobal("fetch", fetchMock)

    await expect(call()).resolves.toEqual([{ id: 1 }])
    expect(fetchMock).toHaveBeenCalledWith(url, { method: "GET", credentials: "include" })
  })

  it("throws on a non-OK response", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response("", { status: 500 })))
    await expect(call()).rejects.toThrow(/status 500/)
  })
})

describe("bespoke endpoints", () => {
  it("login returns the tenant's default locale on success", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(jsonResponse(200, { defaultLocale: "en" })))
    await expect(api.login({ email: "a@b.nl", password: "x" })).resolves.toEqual({ success: true, data: { defaultLocale: "en" } })
  })

  it("login falls back to the default locale on an unsupported value", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(jsonResponse(200, { defaultLocale: "xx" })))
    await expect(api.login({ email: "a@b.nl", password: "x" })).resolves.toEqual({ success: true, data: { defaultLocale: "nl" } })
  })

  it("adminLogin succeeds without parsing a body", async () => {
    // new Response("", { status: 204 }) gooit een TypeError in Node
    // (null-body-status accepteert geen body) — daarom null.
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response(null, { status: 204 })))
    await expect(api.adminLogin({ email: "a@b.nl", password: "x" })).resolves.toEqual({ success: true })
  })

  it("getMe returns null instead of throwing on a non-OK response", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response(null, { status: 401 })))
    await expect(api.getMe()).resolves.toBeNull()
  })

  it("getProfilePhotoBlob returns null on 404 and a Blob on success", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response(null, { status: 404 })))
    await expect(api.getProfilePhotoBlob()).resolves.toBeNull()

    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response(new Blob(["img"]), { status: 200 })))
    await expect(api.getProfilePhotoBlob()).resolves.toBeInstanceOf(Blob)
  })

  it("fetchTenantLoginImageUrl fetches WITHOUT credentials and returns an object URL", async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(new Blob(["img"]), { status: 200 }))
    vi.stubGlobal("fetch", fetchMock)
    vi.spyOn(URL, "createObjectURL").mockReturnValue("blob:x")

    await expect(api.fetchTenantLoginImageUrl()).resolves.toBe("blob:x")
    // Tweede fetch-argument is undefined: géén credentials — het Origin/ORB-
    // gedrag (zie het docblock in de bron) hangt aan een kale fetch().
    expect(fetchMock.mock.calls[0][1]).toBeUndefined()
  })

  it("logout and adminLogout swallow network errors (best effort)", async () => {
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new TypeError("offline")))
    await expect(api.logout()).resolves.toBeUndefined()
    await expect(api.adminLogout()).resolves.toBeUndefined()
  })
})
