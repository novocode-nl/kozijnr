import { z } from "zod"

import type { Locale } from "@/lib/i18n/locale"
import { translateRequired } from "@/lib/i18n/translate"

/**
 * Single source of truth for the admin-user *create* form shape (KOZ-30): a
 * single email address. Mirrors `buildCreateTenantFormSchema`'s `adminEmail`
 * field in lib/schemas/tenant.ts — same locale-aware factory pattern (KOZ-29)
 * so validation messages follow the visitor's chosen language, reusing the
 * `users.error.*` catalog keys the backend's own `errorKey` resolves to.
 */
export function buildCreateAdminUserFormSchema(locale: Locale) {
  return z.object({
    email: z.email(translateRequired("users.error.emailInvalid", locale)),
  })
}

export const createAdminUserFormSchema = buildCreateAdminUserFormSchema("nl")

export type CreateAdminUserFormValues = z.infer<typeof createAdminUserFormSchema>
