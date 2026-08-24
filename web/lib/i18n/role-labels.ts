import type { TFunction } from "i18next"

/**
 * KOZ-29 rework: a tenant user's `roles` array (e.g. `["ROLE_TENANT_USER"]`,
 * see `TenantUser::ROLE_TENANT_ADMIN`/`DEFAULT_ROLE` on the backend) is a
 * small, fixed set of backend-defined constants, not user-generated
 * content — so, like the backend `errorKey`s this ticket already maps
 * through `lib/i18n/translate.ts`, a raw role constant is translated
 * frontend-side via `users.role.*` rather than shown as-is. Falls back to
 * the raw constant for any role not (yet) in the catalog, so an unmapped
 * role still renders something instead of an empty string.
 */
export function roleLabel(role: string, t: TFunction): string {
  return t(`users.role.${role}`, { defaultValue: role })
}
