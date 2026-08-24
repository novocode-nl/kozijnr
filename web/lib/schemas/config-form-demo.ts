import { z } from "zod"

import type { Locale } from "@/lib/i18n/locale"
import { translateRequired } from "@/lib/i18n/translate"

/**
 * Demo schema for the generic `<ConfigForm>` component. Exercises every
 * field type plus `.superRefine()`: `companyName` is only required when
 * `accountType` is `"business"` — the schema enforces the rule, the field
 * config's `visibleWhen` only controls whether the field is shown.
 *
 * KOZ-29 rework: a factory, not a static schema (see lib/schemas/login.ts
 * and lib/schemas/tenant.ts for the same pattern), so its issue messages
 * follow the visitor's chosen language. The demo page rebuilds it from
 * `i18n.language` via `useMemo`.
 */
export function buildConfigFormDemoSchema(locale: Locale) {
  return z
    .object({
      fullName: z.string().min(1, translateRequired("validation.nameRequired", locale)),
      email: z.email(translateRequired("validation.emailInvalid", locale)),
      accountType: z.enum(["personal", "business"]),
      companyName: z.string().optional(),
      plan: z.string().min(1, translateRequired("validation.planRequired", locale)),
      interests: z.array(z.string()).min(1, translateRequired("validation.interestsRequired", locale)),
      bio: z.string().max(280, translateRequired("validation.bioTooLong", locale, { max: 280 })).optional(),
      subscribeToNewsletter: z.boolean(),
      contactPreference: z.string().min(1, translateRequired("validation.contactPreferenceRequired", locale)),
    })
    .superRefine((values, ctx) => {
      if (values.accountType === "business" && !values.companyName?.trim()) {
        ctx.addIssue({
          code: "custom",
          path: ["companyName"],
          message: translateRequired("validation.companyNameRequired", locale),
        })
      }
    })
}

export const configFormDemoSchema = buildConfigFormDemoSchema("nl")

export type ConfigFormDemoValues = z.infer<typeof configFormDemoSchema>
