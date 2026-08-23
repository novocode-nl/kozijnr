import { z } from "zod"

/**
 * Single source of truth for the tenant *edit* form shape: a free-text
 * display `name` and a URL-safe `slug` (the subdomain). The `slug` pattern
 * mirrors the backend's `TenantName` whitelist
 * (api/src/Tenancy/Domain/TenantName.php) so an obviously-invalid slug is
 * caught client-side before ever reaching the API — the API re-validates
 * independently regardless.
 */
export const tenantFormSchema = z.object({
  name: z
    .string()
    .min(1, "Naam is verplicht.")
    .max(255, "Naam mag maximaal 255 tekens lang zijn."),
  slug: z
    .string()
    .min(1, "Subdomain is verplicht.")
    .max(55, "Subdomain mag maximaal 55 tekens lang zijn.")
    .regex(
      /^[a-z0-9]+(-[a-z0-9]+)*$/,
      "Gebruik alleen kleine letters, cijfers en koppeltekens (bijv. \"acme\" of \"acme-bv\")."
    ),
})

export type TenantFormValues = z.infer<typeof tenantFormSchema>

/**
 * The tenant *create* form shape: everything from `tenantFormSchema`, plus
 * the tenant-admin's email — KOZ-27 rework: the operator now chooses this
 * address (it used to be auto-generated from the subdomain) instead of
 * being auto-generated. The admin's password stays auto-generated, shown
 * once via `TenantAdminCredentialsDialog`.
 */
export const createTenantFormSchema = tenantFormSchema.extend({
  adminEmail: z.email("Vul een geldig e-mailadres in."),
})

export type CreateTenantFormValues = z.infer<typeof createTenantFormSchema>
