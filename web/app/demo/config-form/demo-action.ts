"use client"

import type { ActionResult, FormAction } from "@/lib/forms/types"
import type { ConfigFormDemoValues } from "@/lib/schemas/config-form-demo"

/**
 * Fake submit `Action` for the `<ConfigForm>` demo page. Not a real API
 * call (this ticket is UI-component-only, see `KOZ-17`'s "out of scope") —
 * just enough to exercise the component's pending state, generic form
 * error, and field-level error mapping end-to-end:
 * - the e-mail "taken@example.nl" comes back as a field-level error on
 *   `email`, the "e-mailadres al in gebruik" case from the DoD
 * - anything else succeeds after a short artificial delay
 */
export const submitDemoForm: FormAction<ConfigFormDemoValues, ConfigFormDemoValues> = async (
  values
) => {
  await new Promise((resolve) => setTimeout(resolve, 600))

  if (values.email.toLowerCase() === "taken@example.nl") {
    const result: ActionResult<ConfigFormDemoValues> = {
      success: false,
      fieldErrors: { email: "Dit e-mailadres is al in gebruik." },
    }
    return result
  }

  return { success: true, data: values }
}
