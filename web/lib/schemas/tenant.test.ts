import { describe, expect, it } from "vitest"

import { tenantFormSchema } from "@/lib/schemas/tenant"

describe("tenantFormSchema", () => {
  it("accepts a simple lowercase subdomain", () => {
    expect(tenantFormSchema.safeParse({ subdomain: "acme" }).success).toBe(true)
  })

  it("accepts a hyphenated subdomain", () => {
    expect(tenantFormSchema.safeParse({ subdomain: "acme-bv" }).success).toBe(true)
  })

  it("rejects an empty subdomain", () => {
    const result = tenantFormSchema.safeParse({ subdomain: "" })

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues[0]?.message).toBe("Subdomain is verplicht.")
    }
  })

  it("rejects uppercase characters", () => {
    expect(tenantFormSchema.safeParse({ subdomain: "Acme" }).success).toBe(false)
  })

  it("rejects spaces and other invalid characters", () => {
    expect(tenantFormSchema.safeParse({ subdomain: "not valid!" }).success).toBe(false)
  })

  it("rejects a leading or trailing hyphen", () => {
    expect(tenantFormSchema.safeParse({ subdomain: "-acme" }).success).toBe(false)
    expect(tenantFormSchema.safeParse({ subdomain: "acme-" }).success).toBe(false)
  })

  it("rejects a subdomain longer than 55 characters", () => {
    const tooLong = "a".repeat(56)

    expect(tenantFormSchema.safeParse({ subdomain: tooLong }).success).toBe(false)
  })

  it("accepts a subdomain exactly 55 characters long", () => {
    const maxLength = "a".repeat(55)

    expect(tenantFormSchema.safeParse({ subdomain: maxLength }).success).toBe(true)
  })
})
