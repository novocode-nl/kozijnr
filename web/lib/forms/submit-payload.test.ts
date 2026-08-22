import { describe, expect, it } from "vitest"

import { resolveSubmitPayload } from "@/lib/forms/submit-payload"

describe("resolveSubmitPayload", () => {
  it("returns the validated values unchanged when no transformSubmit is given", () => {
    const values = { email: "a@b.nl", password: "secret" }

    expect(resolveSubmitPayload(values)).toBe(values)
  })

  it("applies transformSubmit when provided, independent of the zod schema", () => {
    const values = { email: "a@b.nl", agreeToTerms: true }

    const payload = resolveSubmitPayload(values, (v) => ({
      emailAddress: v.email,
    }))

    expect(payload).toEqual({ emailAddress: "a@b.nl" })
  })
})
