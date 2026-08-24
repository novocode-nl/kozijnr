import { describe, expect, it } from "vitest"

import {
  buildCreateTenantFormSchema,
  buildTenantFormSchema,
  createTenantFormSchema,
  tenantFormSchema,
} from "@/lib/schemas/tenant"

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

describe("createTenantFormSchema", () => {
  const validCreate = { ...valid, adminEmail: "beheerder@acme.nl" }

  it("accepts a valid admin email", () => {
    expect(createTenantFormSchema.safeParse(validCreate).success).toBe(true)
  })

  it("rejects an empty admin email", () => {
    expect(createTenantFormSchema.safeParse({ ...validCreate, adminEmail: "" }).success).toBe(false)
  })

  it("rejects a malformed admin email", () => {
    expect(createTenantFormSchema.safeParse({ ...validCreate, adminEmail: "not-an-email" }).success).toBe(false)
  })

  it("still enforces the shared name/slug rules", () => {
    expect(createTenantFormSchema.safeParse({ ...validCreate, slug: "Not Valid!" }).success).toBe(false)
  })
})

/**
 * KOZ-29 rework: `tenantFormSchema`/`createTenantFormSchema` follow the
 * visitor's chosen language — `buildTenantFormSchema`/
 * `buildCreateTenantFormSchema` are the locale-aware factories they're
 * bound from (see that file's doc comment). Proves the same validation
 * failure renders a different, non-empty message in nl vs en.
 */
describe("buildTenantFormSchema locale awareness", () => {
  it("renders an empty-name error differently in nl vs en", () => {
    const nlResult = buildTenantFormSchema("nl").safeParse({ ...valid, name: "" })
    const enResult = buildTenantFormSchema("en").safeParse({ ...valid, name: "" })

    expect(nlResult.success).toBe(false)
    expect(enResult.success).toBe(false)
    if (!nlResult.success && !enResult.success) {
      const nlMessage = nlResult.error.issues[0]?.message
      const enMessage = enResult.error.issues[0]?.message
      expect(nlMessage).toBe("Naam is verplicht.")
      expect(enMessage).toBe("Name is required.")
      expect(nlMessage).not.toBe(enMessage)
    }
  })

  it("renders an invalid-slug pattern error differently in nl vs en", () => {
    const nlResult = buildTenantFormSchema("nl").safeParse({ ...valid, slug: "Not Valid!" })
    const enResult = buildTenantFormSchema("en").safeParse({ ...valid, slug: "Not Valid!" })

    expect(nlResult.success).toBe(false)
    expect(enResult.success).toBe(false)
    if (!nlResult.success && !enResult.success) {
      const nlMessage = nlResult.error.issues[0]?.message
      const enMessage = enResult.error.issues[0]?.message
      expect(nlMessage).not.toBe(enMessage)
      expect(nlMessage).toBeTruthy()
      expect(enMessage).toBeTruthy()
    }
  })

  it("renders an invalid admin-email error differently in nl vs en", () => {
    const nlResult = buildCreateTenantFormSchema("nl").safeParse({ ...valid, adminEmail: "not-an-email" })
    const enResult = buildCreateTenantFormSchema("en").safeParse({ ...valid, adminEmail: "not-an-email" })

    expect(nlResult.success).toBe(false)
    expect(enResult.success).toBe(false)
    if (!nlResult.success && !enResult.success) {
      const nlMessage = nlResult.error.issues[0]?.message
      const enMessage = enResult.error.issues[0]?.message
      expect(nlMessage).toBe("Voer een geldig e-mailadres in voor de tenant-beheerder.")
      expect(enMessage).toBe("Enter a valid email address for the tenant administrator.")
      expect(nlMessage).not.toBe(enMessage)
    }
  })
})
