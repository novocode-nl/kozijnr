import { z } from "zod"

import { SUPPORTED_LOCALES } from "@/lib/i18n/locale"
import type { Locale } from "@/lib/i18n/locale"
import { translateRequired } from "@/lib/i18n/translate"

/**
 * Single source of truth for the tenant-settings "default language" form
 * shape (KOZ-34): a choice between the locales KOZ-29's i18n
 * infrastructure actually ships translations for
 * (lib/i18n/locale.ts::SUPPORTED_LOCALES) — mirrors the backend's own
 * `Tenant::SUPPORTED_LOCALES` allowlist. Same factory pattern as
 * buildCreateTenantUserFormSchema, for the same reason (localized issue
 * messages).
 */
export function buildTenantLocaleFormSchema(locale: Locale) {
  return z.object({
    defaultLocale: z.enum(SUPPORTED_LOCALES, translateRequired("tenants.error.invalidLocale", locale)),
  })
}

export const tenantLocaleFormSchema = buildTenantLocaleFormSchema("nl")

export type TenantLocaleFormValues = z.infer<typeof tenantLocaleFormSchema>
