/**
 * Mirrors the two role constants on the backend's TenantUser
 * (App\TenantUser\Domain\TenantUser::ROLE_TENANT_ADMIN / DEFAULT_ROLE) —
 * the only two roles a tenant user can have. KOZ-31 adds no new roles,
 * only the frontend ability to choose between these two when creating a
 * tenant user. Kept as one small constants module so the "add user" form
 * schema (lib/schemas/tenant-user.ts) and dialog (components/tenants/
 * tenant-user-form-dialog.tsx) share a single source instead of each
 * hardcoding the two role strings.
 */
export const TenantUser = {
  ROLE_TENANT_ADMIN: "ROLE_TENANT_ADMIN",
  ROLE_TENANT_USER: "ROLE_TENANT_USER",
} as const
