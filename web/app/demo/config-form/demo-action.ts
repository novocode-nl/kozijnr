"use client"

import type { ActionResult, FormAction } from "@/lib/forms/types"
import type { ConfigFormDemoValues } from "@/lib/schemas/config-form-demo"
import { getClientLocale } from "@/lib/i18n/locale"
import { translateRequired } from "@/lib/i18n/translate"

/** Fake submit action for the `<ConfigForm>` demo — not a real API call. */
export const submitDemoForm: FormAction<ConfigFormDemoValues, ConfigFormDemoValues> = async (
  values
) => {
  await new Promise((resolve) => setTimeout(resolve, 600))

  if (values.email.toLowerCase() === "taken@example.nl") {
    const result: ActionResult<ConfigFormDemoValues> = {
      success: false,
      fieldErrors: { email: translateRequired("demo.emailTakenError", getClientLocale()) },
    }
    return result
  }

  return { success: true, data: values }
}
