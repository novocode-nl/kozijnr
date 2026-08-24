import { z } from "zod"

import { TenantUser } from "@/lib/domain/tenant-user-roles"
import type { Locale } from "@/lib/i18n/locale"
import { translateRequired } from "@/lib/i18n/translate"

/**
 * Single source of truth for the "add a tenant user" form shape (KOZ-31):
 * an email address plus a choice between the two roles that already exist
 * on the backend's TenantUser (App\TenantUser\Domain\TenantUser) —
 * ROLE_TENANT_ADMIN and ROLE_TENANT_USER. No new roles are introduced here,
 * only the ability to pick between the two that already exist.
 *
 * A factory (not a static schema), like buildTenantFormSchema, so its issue
 * messages follow the visitor's chosen language. Reuses the same
 * `tenants.error.*` catalog keys the backend's own errorKeys resolve to
 * (lib/i18n/translate.ts) rather than a separate set of client-only
 * strings, since they describe the same rules.
 */
export function buildCreateTenantUserFormSchema(locale: Locale) {
  return z.object({
    email: z.email(translateRequired("tenants.error.userEmailInvalid", locale)),
    role: z.enum(
      [TenantUser.ROLE_TENANT_ADMIN, TenantUser.ROLE_TENANT_USER],
      translateRequired("tenants.error.userRoleInvalid", locale)
    ),
  })
}

export const createTenantUserFormSchema = buildCreateTenantUserFormSchema("nl")

export type CreateTenantUserFormValues = z.infer<typeof createTenantUserFormSchema>
