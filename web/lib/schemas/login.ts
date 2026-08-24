import { z } from "zod"

import type { Locale } from "@/lib/i18n/locale"
import { translateRequired } from "@/lib/i18n/translate"

/**
 * Single source of truth for the tenant-user login form shape. Client-side
 * validation only — the backend re-validates independently.
 *
 * KOZ-29 rework: a factory, not a static schema, so its issue messages
 * follow the visitor's chosen language (`translate()`, the same
 * locale-lookup lib/api.ts already uses for backend error keys) instead of
 * always being Dutch. Callers (login-form.tsx, admin-login-form.tsx)
 * rebuild it from `i18n.language` via `useMemo`.
 */
export function buildLoginSchema(locale: Locale) {
  return z.object({
    email: z.email(translateRequired("validation.emailInvalid", locale)),
    password: z.string().min(1, translateRequired("validation.passwordRequired", locale)),
  })
}

export const loginSchema = buildLoginSchema("nl")

export type LoginFormValues = z.infer<typeof loginSchema>
