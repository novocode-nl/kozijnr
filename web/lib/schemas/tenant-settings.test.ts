import { describe, expect, it } from "vitest"

import { buildTenantLocaleFormSchema, tenantLocaleFormSchema } from "@/lib/schemas/tenant-settings"

describe("tenantLocaleFormSchema", () => {
  it("accepts every supported locale", () => {
    expect(tenantLocaleFormSchema.safeParse({ defaultLocale: "nl" }).success).toBe(true)
    expect(tenantLocaleFormSchema.safeParse({ defaultLocale: "en" }).success).toBe(true)
  })

  it("rejects an unsupported locale", () => {
    const result = tenantLocaleFormSchema.safeParse({ defaultLocale: "fr" })

    expect(result.success).toBe(false)
  })

  it("builds localized issue messages", () => {
    const nlSchema = buildTenantLocaleFormSchema("nl")
    const result = nlSchema.safeParse({ defaultLocale: "fr" })

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues[0]?.message).toBe("Kies een geldige taal.")
    }
  })
})
