import { z } from "zod"

/**
 * Single source of truth for the tenant-user login form shape (KOZ-13).
 * Client-side validation only, ahead of the call to POST /api/login
 * (KOZ-11) — the backend re-validates independently, this only prevents an
 * obviously-invalid submission from ever reaching the network.
 */
export const loginSchema = z.object({
  email: z.email("Vul een geldig e-mailadres in."),
  password: z.string().min(1, "Wachtwoord is verplicht."),
})

export type LoginFormValues = z.infer<typeof loginSchema>
