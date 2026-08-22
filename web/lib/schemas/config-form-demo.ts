import { z } from "zod"

/**
 * Demo schema for the generic `<ConfigForm>` component. Exercises every
 * field type plus `.superRefine()`: `companyName` is only required when
 * `accountType` is `"business"` — the schema enforces the rule, the field
 * config's `visibleWhen` only controls whether the field is shown.
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
    contactPreference: z.string().min(1, "Kies een contactvoorkeur."),
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
