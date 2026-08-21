import { describe, expect, it } from "vitest"

import { apiBaseUrl } from "@/lib/api-base-url"

describe("apiBaseUrl", () => {
  it("replaces the first host label with api", () => {
    expect(apiBaseUrl("admin.kozijnr.localhost")).toBe("http://api.kozijnr.localhost")
    expect(apiBaseUrl("acme.koz-16.kozijnr.localhost")).toBe("http://api.koz-16.kozijnr.localhost")
  })

  it("keeps an explicit port and protocol", () => {
    expect(apiBaseUrl("admin.kozijnr.localhost:8080", "https:")).toBe("https://api.kozijnr.localhost:8080")
  })

  it("is idempotent on an api host", () => {
    expect(apiBaseUrl("api.kozijnr.localhost")).toBe("http://api.kozijnr.localhost")
  })

  it("works for a production apex", () => {
    expect(apiBaseUrl("admin.kozijnr.nl", "https:")).toBe("https://api.kozijnr.nl")
  })
})
