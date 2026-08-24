import { z } from "zod"

import type { Locale } from "@/lib/i18n/locale"
import { translateRequired } from "@/lib/i18n/translate"

/**
 * Single source of truth for the tenant *edit* form shape: a free-text
 * display `name` and a URL-safe `slug` (the subdomain). The `slug` pattern
 * mirrors the backend's `TenantName` whitelist
 * (api/src/Tenancy/Domain/TenantName.php) so an obviously-invalid slug is
 * caught client-side before ever reaching the API — the API re-validates
 * independently regardless.
 *
 * KOZ-29 rework: a factory, not a static schema, so its issue messages
 * follow the visitor's chosen language. Reuses the same `tenants.error.*`
 * catalog keys the backend's own errorKeys resolve to (lib/i18n/translate.ts)
 * rather than a separate set of client-only strings, since they describe
 * the same rules. Callers (tenant-form-dialog.tsx) rebuild it from
 * `i18n.language` via `useMemo`.
 */
export function buildTenantFormSchema(locale: Locale) {
  return z.object({
    name: z
      .string()
      .min(1, translateRequired("tenants.error.nameRequired", locale))
      .max(255, translateRequired("tenants.error.nameTooLong", locale, { max: 255 })),
    slug: z
      .string()
      .min(1, translateRequired("tenants.error.subdomainRequired", locale))
      .max(55, translateRequired("tenants.error.subdomainTooLong", locale, { max: 55 }))
      .regex(/^[a-z0-9]+(-[a-z0-9]+)*$/, translateRequired("tenants.error.subdomainPattern", locale)),
  })
}

export const tenantFormSchema = buildTenantFormSchema("nl")

export type TenantFormValues = z.infer<typeof tenantFormSchema>

/**
 * The tenant *create* form shape: everything from `tenantFormSchema`, plus
 * the tenant-admin's email — KOZ-27 rework: the operator now chooses this
 * address (it used to be auto-generated from the subdomain) instead of
 * being auto-generated. The admin's password stays auto-generated, shown
 * once via `TenantAdminCredentialsDialog`.
 */
export function buildCreateTenantFormSchema(locale: Locale) {
  return buildTenantFormSchema(locale).extend({
    adminEmail: z.email(translateRequired("tenants.error.adminEmailInvalid", locale)),
  })
}

export const createTenantFormSchema = buildCreateTenantFormSchema("nl")

export type CreateTenantFormValues = z.infer<typeof createTenantFormSchema>
