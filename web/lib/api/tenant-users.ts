import { getClientLocale } from "@/lib/i18n/locale"
import { translate } from "@/lib/i18n/translate"
import type { ActionResult } from "@/lib/forms/types"

import { getJsonOrThrow, requestAction } from "./http"

/** One tenant user, as returned by GET /api/admin/tenants/{subdomain}/users. */
export type TenantUserSummary = {
  email: string
  roles: string[]
}

/**
 * Payload shape CreateTenantUserController expects: an email address plus
 * one of the two roles that exist on the backend's TenantUser
 * (ROLE_TENANT_ADMIN / ROLE_TENANT_USER).
 */
export type CreateTenantUserPayload = { email: string; role: string }

/**
 * Response shape from POST /api/admin/tenants/{subdomain}/users: the
 * created tenant user plus its generated one-time password (KOZ-31),
 * mirroring `CreatedTenant`'s `tenantAdmin` shape.
 */
export type CreatedTenantUser = TenantUserSummary & { password: string }

// Translated lazily, same reasoning as the tenants module's helpers.
function tenantUserCreateFailedMessage(): string {
  return translate("tenants.error.createFailed", getClientLocale()) ?? "Failed to create tenant user."
}

/**
 * Tenant users tab: GET /api/admin/tenants/{subdomain}/users, guarded
 * backend-side by the `tenant:users:list` permission (see
 * ListTenantUsersController).
 */
export async function listTenantUsers(subdomain: string): Promise<TenantUserSummary[]> {
  return getJsonOrThrow(`/api/admin/tenants/${encodeURIComponent(subdomain)}/users`, "tenant users")
}

/**
 * Tenant-own self-service users list (KOZ-31 rework): GET /api/users,
 * reachable by any authenticated tenant user on their own tenant
 * subdomain (see App\TenantUser\Infrastructure\Controller\ListOwnTenantUsersController).
 * Unlike `listTenantUsers`, takes no subdomain — the tenant is decided by
 * whichever subdomain the request itself is on.
 */
export async function listOwnTenantUsers(): Promise<TenantUserSummary[]> {
  return getJsonOrThrow("/api/users", "tenant users")
}

/**
 * "Gebruiker toevoegen" action on the tenant users tab (KOZ-31): POST
 * /api/admin/tenants/{subdomain}/users, guarded backend-side by the
 * `tenant:users:create` permission (see CreateTenantUserController). The
 * backend reports failures (invalid email, invalid role, duplicate email
 * within the tenant) as a single `{ message, errorKey }`, not a field-keyed
 * map — attached to `email` here (the field every one of those failures is
 * actually about) so `<ConfigForm>` shows it in the right place.
 */
export async function createTenantUser(
  subdomain: string,
  payload: CreateTenantUserPayload
): Promise<ActionResult<CreatedTenantUser>> {
  return requestAction(`/api/admin/tenants/${encodeURIComponent(subdomain)}/users`, {
    json: payload,
    fallbackMessage: tenantUserCreateFailedMessage,
    errorField: "email",
  })
}

/**
 * "Gebruiker toevoegen" action on the tenant-own "Gebruikers" page (KOZ-31
 * rework): POST /api/users, guarded backend-side by the ROLE_TENANT_ADMIN
 * role (see CreateOwnTenantUserController). Mirrors `createTenantUser`'s
 * error-mapping but never takes a subdomain — the tenant context comes
 * exclusively from the logged-in session's own subdomain.
 */
export async function createOwnTenantUser(
  payload: CreateTenantUserPayload
): Promise<ActionResult<CreatedTenantUser>> {
  return requestAction("/api/users", {
    json: payload,
    fallbackMessage: tenantUserCreateFailedMessage,
    errorField: "email",
  })
}
