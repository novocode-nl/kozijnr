import { z } from "zod"

/**
 * Single source of truth for the tenant-user login form shape. Client-side
 * validation only — the backend re-validates independently.
 */
export const loginSchema = z.object({
  email: z.email("Vul een geldig e-mailadres in."),
  password: z.string().min(1, "Wachtwoord is verplicht."),
})

export type LoginFormValues = z.infer<typeof loginSchema>
