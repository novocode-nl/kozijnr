import { describe, expect, it } from "vitest"

import { getVisibleFields, isFieldVisible } from "@/lib/forms/visibility"
import type { FieldConfig } from "@/lib/forms/types"

type Values = { plan: string; seats?: number; note?: string }

describe("isFieldVisible", () => {
  it("is visible when the field has no visibleWhen condition", () => {
    const field: FieldConfig<Values> = { name: "plan", label: "Plan", type: "text" }

    expect(isFieldVisible(field, { plan: "team" })).toBe(true)
  })

  it("evaluates visibleWhen against the current form values", () => {
    const field: FieldConfig<Values> = {
      name: "seats",
      label: "Seats",
      type: "number",
      visibleWhen: (values) => values.plan === "team",
    }

    expect(isFieldVisible(field, { plan: "team" })).toBe(true)
    expect(isFieldVisible(field, { plan: "solo" })).toBe(false)
  })
})

describe("getVisibleFields", () => {
  it("filters out fields whose visibleWhen returns false", () => {
    const fields: FieldConfig<Values>[] = [
      { name: "plan", label: "Plan", type: "text" },
      {
        name: "seats",
        label: "Seats",
        type: "number",
        visibleWhen: (values) => values.plan === "team",
      },
      {
        name: "note",
        label: "Note",
        type: "textarea",
        visibleWhen: (values) => values.plan === "solo",
      },
    ]

    expect(getVisibleFields(fields, { plan: "team" }).map((f) => f.name)).toEqual([
      "plan",
      "seats",
    ])
    expect(getVisibleFields(fields, { plan: "solo" }).map((f) => f.name)).toEqual([
      "plan",
      "note",
    ])
  })

  it("returns an empty array when given no fields", () => {
    expect(getVisibleFields([], { plan: "team" })).toEqual([])
  })
})
