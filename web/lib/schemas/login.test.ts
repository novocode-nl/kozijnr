import { describe, expect, it } from "vitest"

import { buildLoginSchema, loginSchema } from "@/lib/schemas/login"

describe("loginSchema", () => {
  it("accepts a valid email/password pair", () => {
    expect(loginSchema.safeParse({ email: "jan@acme.nl", password: "secret" }).success).toBe(true)
  })

  it("rejects a malformed email", () => {
    expect(loginSchema.safeParse({ email: "not-an-email", password: "secret" }).success).toBe(false)
  })

  it("rejects an empty password", () => {
    expect(loginSchema.safeParse({ email: "jan@acme.nl", password: "" }).success).toBe(false)
  })
})

/**
 * KOZ-29 rework: `loginSchema` follows the visitor's chosen language —
 * `buildLoginSchema` is the locale-aware factory it's bound from (see that
 * file's doc comment).
 */
describe("buildLoginSchema locale awareness", () => {
  it("renders the invalid-email error differently in nl vs en", () => {
    const nlResult = buildLoginSchema("nl").safeParse({ email: "not-an-email", password: "secret" })
    const enResult = buildLoginSchema("en").safeParse({ email: "not-an-email", password: "secret" })

    expect(nlResult.success).toBe(false)
    expect(enResult.success).toBe(false)
    if (!nlResult.success && !enResult.success) {
      const nlMessage = nlResult.error.issues[0]?.message
      const enMessage = enResult.error.issues[0]?.message
      expect(nlMessage).toBe("Vul een geldig e-mailadres in.")
      expect(enMessage).toBe("Enter a valid email address.")
      expect(nlMessage).not.toBe(enMessage)
    }
  })

  it("renders the required-password error differently in nl vs en", () => {
    const nlResult = buildLoginSchema("nl").safeParse({ email: "jan@acme.nl", password: "" })
    const enResult = buildLoginSchema("en").safeParse({ email: "jan@acme.nl", password: "" })

    expect(nlResult.success).toBe(false)
    expect(enResult.success).toBe(false)
    if (!nlResult.success && !enResult.success) {
      const nlMessage = nlResult.error.issues[0]?.message
      const enMessage = enResult.error.issues[0]?.message
      expect(nlMessage).toBe("Wachtwoord is verplicht.")
      expect(enMessage).toBe("Password is required.")
      expect(nlMessage).not.toBe(enMessage)
    }
  })
})
