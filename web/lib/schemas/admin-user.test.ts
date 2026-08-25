import { describe, expect, it } from "vitest"

import { buildCreateAdminUserFormSchema, createAdminUserFormSchema } from "@/lib/schemas/admin-user"

describe("createAdminUserFormSchema", () => {
  it("accepts a valid email", () => {
    expect(createAdminUserFormSchema.safeParse({ email: "new-admin@kozijnr.nl" }).success).toBe(true)
  })

  it("rejects an empty email", () => {
    expect(createAdminUserFormSchema.safeParse({ email: "" }).success).toBe(false)
  })

  it("rejects a malformed email", () => {
    expect(createAdminUserFormSchema.safeParse({ email: "not-an-email" }).success).toBe(false)
  })
})

/**
 * KOZ-29 rework: locale-aware factory, same pattern as
 * buildTenantFormSchema — proves the same validation failure renders a
 * different, non-empty message in nl vs en.
 */
describe("buildCreateAdminUserFormSchema locale awareness", () => {
  it("renders an invalid-email error differently in nl vs en", () => {
    const nlResult = buildCreateAdminUserFormSchema("nl").safeParse({ email: "not-an-email" })
    const enResult = buildCreateAdminUserFormSchema("en").safeParse({ email: "not-an-email" })

    expect(nlResult.success).toBe(false)
    expect(enResult.success).toBe(false)
    if (!nlResult.success && !enResult.success) {
      const nlMessage = nlResult.error.issues[0]?.message
      const enMessage = enResult.error.issues[0]?.message
      expect(nlMessage).toBeTruthy()
      expect(enMessage).toBeTruthy()
      expect(nlMessage).not.toBe(enMessage)
    }
  })
})
