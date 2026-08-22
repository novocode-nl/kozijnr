import { z } from "zod"

/**
 * Demo schema for the generic `<ConfigForm>` component (KOZ-17). Single
 * source of truth for the form's shape and validation, per this project's
 * Zod conventions — see `lib/schemas/login.ts`.
 *
 * Exercises every field type the component supports plus the DoD's
 * `.superRefine()` requirement: `companyName` is only required when
 * `accountType` is `"business"`, which also drives a `visibleWhen`
 * condition on the corresponding field in the demo's field config
 * (`app/demo/config-form/page.tsx`) — the schema enforces the rule, the
 * config only controls whether the field is shown.
 */
export const configFormDemoSchema = z
  .object({
    fullName: z.string().min(1, "Naam is verplicht."),
    email: z.email("Vul een geldig e-mailadres in."),
    accountType: z.enum(["personal", "business"]),
    companyName: z.string().optional(),
    plan: z.string().min(1, "Kies een abonnement."),
    interests: z.array(z.string()).min(1, "Kies minimaal één interesse."),
    bio: z.string().max(280, "Maximaal 280 tekens.").optional(),
    subscribeToNewsletter: z.boolean(),
  })
  .superRefine((values, ctx) => {
    if (values.accountType === "business" && !values.companyName?.trim()) {
      ctx.addIssue({
        code: "custom",
        path: ["companyName"],
        message: "Bedrijfsnaam is verplicht voor een zakelijk account.",
      })
    }
  })

export type ConfigFormDemoValues = z.infer<typeof configFormDemoSchema>
