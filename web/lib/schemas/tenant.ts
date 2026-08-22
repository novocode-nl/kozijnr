import { z } from "zod"

/**
 * Single source of truth for the tenant create/edit form shape: a
 * free-text display `name` and a URL-safe `slug` (the subdomain). The
 * `slug` pattern mirrors the backend's `TenantName` whitelist
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
