import { describe, expect, it, vi } from "vitest"

import { applyFieldErrors } from "@/lib/forms/field-errors"

describe("applyFieldErrors", () => {
  it("does nothing when there are no field errors", () => {
    const setError = vi.fn()

    const result = applyFieldErrors(setError, undefined, ["email", "password"])

    expect(setError).not.toHaveBeenCalled()
    expect(result).toEqual({ applied: [], unmatched: {} })
  })

  it("calls setError for every field error whose name matches a known field", () => {
    const setError = vi.fn()

    const result = applyFieldErrors(
      setError,
      { email: "E-mailadres is al in gebruik." },
      ["email", "password"]
    )

    expect(setError).toHaveBeenCalledWith("email", {
      type: "server",
      message: "E-mailadres is al in gebruik.",
    })
    expect(result.applied).toEqual(["email"])
    expect(result.unmatched).toEqual({})
  })

  it("collects errors for unknown field names instead of calling setError", () => {
    const setError = vi.fn()

    const result = applyFieldErrors(
      setError,
      { nonexistent: "Onbekend veld." },
      ["email", "password"]
    )

    expect(setError).not.toHaveBeenCalled()
    expect(result).toEqual({
      applied: [],
      unmatched: { nonexistent: "Onbekend veld." },
    })
  })

  it("handles a mix of known and unknown field names", () => {
    const setError = vi.fn()

    const result = applyFieldErrors(
      setError,
      { email: "Al in gebruik.", surprise: "Huh?" },
      ["email", "password"]
    )

    expect(setError).toHaveBeenCalledTimes(1)
    expect(result.applied).toEqual(["email"])
    expect(result.unmatched).toEqual({ surprise: "Huh?" })
  })
})
