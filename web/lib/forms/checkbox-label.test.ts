import { describe, expect, it } from "vitest"

import { resolveCheckboxLabel } from "@/lib/forms/checkbox-label"

describe("resolveCheckboxLabel", () => {
  it("omits the top-level label when optionLabel is not set, so the label text renders only once", () => {
    const result = resolveCheckboxLabel({ label: "Akkoord" })

    expect(result.topLabel).toBeNull()
    expect(result.optionText).toBe("Akkoord")
  })

  it("keeps both the top-level label and a distinct option text when optionLabel is set", () => {
    const result = resolveCheckboxLabel({
      label: "Nieuwsbrief",
      optionLabel: "Ja, ik wil de nieuwsbrief ontvangen",
    })

    expect(result.topLabel).toBe("Nieuwsbrief")
    expect(result.optionText).toBe("Ja, ik wil de nieuwsbrief ontvangen")
  })
})
