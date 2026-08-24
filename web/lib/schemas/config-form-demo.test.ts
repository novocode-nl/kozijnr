import { describe, expect, it } from "vitest"

import { buildConfigFormDemoSchema, configFormDemoSchema } from "@/lib/schemas/config-form-demo"

const baseValues = {
  fullName: "Jan Jansen",
  email: "jan@example.nl",
  accountType: "personal" as const,
  companyName: "",
  plan: "solo",
  interests: ["design"],
  bio: "",
  subscribeToNewsletter: false,
  contactPreference: "email",
}

describe("configFormDemoSchema", () => {
  it("accepts a valid personal account without a company name", () => {
    const result = configFormDemoSchema.safeParse(baseValues)

    expect(result.success).toBe(true)
  })

  it("rejects a business account without a company name (conditional requirement via superRefine)", () => {
    const result = configFormDemoSchema.safeParse({
      ...baseValues,
      accountType: "business",
      companyName: "",
    })

    expect(result.success).toBe(false)
    if (!result.success) {
      const companyNameIssue = result.error.issues.find(
        (issue) => issue.path.join(".") === "companyName"
      )
      expect(companyNameIssue?.message).toBe(
        "Bedrijfsnaam is verplicht voor een zakelijk account."
      )
    }
  })

  it("accepts a business account once a company name is provided", () => {
    const result = configFormDemoSchema.safeParse({
      ...baseValues,
      accountType: "business",
      companyName: "Acme BV",
    })

    expect(result.success).toBe(true)
  })

  it("requires at least one interest", () => {
    const result = configFormDemoSchema.safeParse({ ...baseValues, interests: [] })

    expect(result.success).toBe(false)
  })

  it("rejects a submission with no contact preference chosen", () => {
    const result = configFormDemoSchema.safeParse({ ...baseValues, contactPreference: "" })

    expect(result.success).toBe(false)
    if (!result.success) {
      const issue = result.error.issues.find(
        (issue) => issue.path.join(".") === "contactPreference"
      )
      expect(issue?.message).toBe("Kies een contactvoorkeur.")
    }
  })

  it("accepts any of the configured contact preference options", () => {
    for (const value of ["email", "phone", "none"]) {
      const result = configFormDemoSchema.safeParse({ ...baseValues, contactPreference: value })

      expect(result.success).toBe(true)
    }
  })
})

/**
 * KOZ-29 rework: `configFormDemoSchema` follows the visitor's chosen
 * language — `buildConfigFormDemoSchema` is the locale-aware factory it's
 * bound from (see that file's doc comment).
 */
describe("buildConfigFormDemoSchema locale awareness", () => {
  it("renders the required-plan error differently in nl vs en", () => {
    const nlResult = buildConfigFormDemoSchema("nl").safeParse({ ...baseValues, plan: "" })
    const enResult = buildConfigFormDemoSchema("en").safeParse({ ...baseValues, plan: "" })

    expect(nlResult.success).toBe(false)
    expect(enResult.success).toBe(false)
    if (!nlResult.success && !enResult.success) {
      const nlMessage = nlResult.error.issues[0]?.message
      const enMessage = enResult.error.issues[0]?.message
      expect(nlMessage).toBe("Kies een abonnement.")
      expect(enMessage).toBe("Choose a plan.")
      expect(nlMessage).not.toBe(enMessage)
    }
  })
})
