import { describe, expect, it } from "vitest"

import { buildCreateTenantUserFormSchema, createTenantUserFormSchema } from "@/lib/schemas/tenant-user"

const valid = { email: "collega@acme.nl", role: "ROLE_TENANT_USER" }

describe("createTenantUserFormSchema", () => {
  it("accepts a valid email with the tenant-user role", () => {
    expect(createTenantUserFormSchema.safeParse(valid).success).toBe(true)
  })

  it("accepts a valid email with the tenant-admin role", () => {
    expect(createTenantUserFormSchema.safeParse({ ...valid, role: "ROLE_TENANT_ADMIN" }).success).toBe(true)
  })

  it("rejects an empty email", () => {
    expect(createTenantUserFormSchema.safeParse({ ...valid, email: "" }).success).toBe(false)
  })

  it("rejects a malformed email", () => {
    expect(createTenantUserFormSchema.safeParse({ ...valid, email: "not-an-email" }).success).toBe(false)
  })

  it("rejects a role outside the two that exist on TenantUser", () => {
    expect(createTenantUserFormSchema.safeParse({ ...valid, role: "ROLE_SUPER_ADMIN" }).success).toBe(false)
  })

  it("rejects a missing role", () => {
    expect(createTenantUserFormSchema.safeParse({ email: valid.email }).success).toBe(false)
  })
})

/**
 * KOZ-29 convention: this schema's issue messages follow the visitor's
 * chosen language, same as buildTenantFormSchema — proves the same
 * validation failure renders a different, non-empty message in nl vs en.
 */
describe("buildCreateTenantUserFormSchema locale awareness", () => {
  it("renders an invalid-email error differently in nl vs en", () => {
    const nlResult = buildCreateTenantUserFormSchema("nl").safeParse({ ...valid, email: "not-an-email" })
    const enResult = buildCreateTenantUserFormSchema("en").safeParse({ ...valid, email: "not-an-email" })

    expect(nlResult.success).toBe(false)
    expect(enResult.success).toBe(false)
    if (!nlResult.success && !enResult.success) {
      const nlMessage = nlResult.error.issues[0]?.message
      const enMessage = enResult.error.issues[0]?.message
      expect(nlMessage).toBe("Voer een geldig e-mailadres in.")
      expect(enMessage).toBe("Enter a valid email address.")
      expect(nlMessage).not.toBe(enMessage)
    }
  })

  it("renders an invalid-role error differently in nl vs en", () => {
    const nlResult = buildCreateTenantUserFormSchema("nl").safeParse({ ...valid, role: "ROLE_SUPER_ADMIN" })
    const enResult = buildCreateTenantUserFormSchema("en").safeParse({ ...valid, role: "ROLE_SUPER_ADMIN" })

    expect(nlResult.success).toBe(false)
    expect(enResult.success).toBe(false)
    if (!nlResult.success && !enResult.success) {
      const nlMessage = nlResult.error.issues[0]?.message
      const enMessage = enResult.error.issues[0]?.message
      expect(nlMessage).toBe("Kies een geldige rol.")
      expect(enMessage).toBe("Choose a valid role.")
      expect(nlMessage).not.toBe(enMessage)
    }
  })
})
