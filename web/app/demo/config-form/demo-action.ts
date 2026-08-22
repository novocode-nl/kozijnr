"use client"

import type { ActionResult, FormAction } from "@/lib/forms/types"
import type { ConfigFormDemoValues } from "@/lib/schemas/config-form-demo"

/** Fake submit action for the `<ConfigForm>` demo — not a real API call. */
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
