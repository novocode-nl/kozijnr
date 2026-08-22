import { describe, expect, it } from "vitest"

import {
  DEFAULT_REDIRECT_PATH,
  buildRedirectTarget,
  isSafeRedirectPath,
  sanitizeRedirectTarget,
} from "@/lib/navigation/safe-redirect"

describe("isSafeRedirectPath", () => {
  it("accepts a plain relative path", () => {
    expect(isSafeRedirectPath("/dashboard")).toBe(true)
  })

  it("accepts a relative path with a query string", () => {
    expect(isSafeRedirectPath("/dashboard/settings?tab=billing")).toBe(true)
  })

  it("rejects an absolute URL", () => {
    expect(isSafeRedirectPath("https://evil.example/phish")).toBe(false)
  })

  it("rejects a scheme-relative URL", () => {
    expect(isSafeRedirectPath("//evil.example")).toBe(false)
  })

  it("rejects a backslash trick", () => {
    expect(isSafeRedirectPath("/\\evil.example")).toBe(false)
  })

  it("rejects a path without a leading slash", () => {
    expect(isSafeRedirectPath("dashboard")).toBe(false)
  })

  it("rejects an empty string", () => {
    expect(isSafeRedirectPath("")).toBe(false)
  })

  it("rejects a javascript: pseudo-scheme", () => {
    expect(isSafeRedirectPath("javascript:alert(1)")).toBe(false)
  })
})

describe("buildRedirectTarget", () => {
  it("joins pathname and search", () => {
    expect(buildRedirectTarget("/dashboard/settings", "?tab=billing")).toBe(
      "/dashboard/settings?tab=billing"
    )
  })

  it("works with no search string", () => {
    expect(buildRedirectTarget("/dashboard", "")).toBe("/dashboard")
  })
})

describe("sanitizeRedirectTarget", () => {
  it("returns a valid relative path unchanged", () => {
    expect(sanitizeRedirectTarget("/dashboard/settings")).toBe("/dashboard/settings")
  })

  it("falls back to the default for null", () => {
    expect(sanitizeRedirectTarget(null)).toBe(DEFAULT_REDIRECT_PATH)
  })

  it("falls back to the default for undefined", () => {
    expect(sanitizeRedirectTarget(undefined)).toBe(DEFAULT_REDIRECT_PATH)
  })

  it("falls back to the default for an empty string", () => {
    expect(sanitizeRedirectTarget("")).toBe(DEFAULT_REDIRECT_PATH)
  })

  it("falls back to the default for an external URL", () => {
    expect(sanitizeRedirectTarget("https://evil.example")).toBe(DEFAULT_REDIRECT_PATH)
  })

  it("falls back to the default for a scheme-relative URL", () => {
    expect(sanitizeRedirectTarget("//evil.example")).toBe(DEFAULT_REDIRECT_PATH)
  })
})
