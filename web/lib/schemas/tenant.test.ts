import { describe, expect, it } from "vitest"

import { tenantFormSchema } from "@/lib/schemas/tenant"

const valid = { name: "Acme B.V.", slug: "acme" }

describe("tenantFormSchema", () => {
  it("accepts a simple lowercase slug", () => {
    expect(tenantFormSchema.safeParse(valid).success).toBe(true)
  })

  it("accepts a hyphenated slug", () => {
    expect(tenantFormSchema.safeParse({ ...valid, slug: "acme-bv" }).success).toBe(true)
  })

  it("rejects an empty name", () => {
    const result = tenantFormSchema.safeParse({ ...valid, name: "" })

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues[0]?.message).toBe("Naam is verplicht.")
    }
  })

  it("rejects an empty slug", () => {
    const result = tenantFormSchema.safeParse({ ...valid, slug: "" })

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues[0]?.message).toBe("Subdomain is verplicht.")
    }
  })

  it("rejects uppercase characters in the slug", () => {
    expect(tenantFormSchema.safeParse({ ...valid, slug: "Acme" }).success).toBe(false)
  })

  it("rejects spaces and other invalid characters in the slug", () => {
    expect(tenantFormSchema.safeParse({ ...valid, slug: "not valid!" }).success).toBe(false)
  })

  it("rejects a leading or trailing hyphen in the slug", () => {
    expect(tenantFormSchema.safeParse({ ...valid, slug: "-acme" }).success).toBe(false)
    expect(tenantFormSchema.safeParse({ ...valid, slug: "acme-" }).success).toBe(false)
  })

  it("rejects a slug longer than 55 characters", () => {
    const tooLong = "a".repeat(56)

    expect(tenantFormSchema.safeParse({ ...valid, slug: tooLong }).success).toBe(false)
  })

  it("accepts a slug exactly 55 characters long", () => {
    const maxLength = "a".repeat(55)

    expect(tenantFormSchema.safeParse({ ...valid, slug: maxLength }).success).toBe(true)
  })

  it("rejects a name longer than 255 characters", () => {
    const tooLong = "a".repeat(256)

    expect(tenantFormSchema.safeParse({ ...valid, name: tooLong }).success).toBe(false)
  })
})
