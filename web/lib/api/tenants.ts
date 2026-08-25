import { getClientLocale } from "@/lib/i18n/locale"
import { translate } from "@/lib/i18n/translate"
import type { ActionResult } from "@/lib/forms/types"

import { getJsonOrThrow, requestAction } from "./http"

/**
 * Read-model shape returned by GET /api/admin/tenants and the
 * create/update/archive/unarchive endpoints (TenantSummary::toArray) —
 * nothing from the tenant's Postgres schema internals leaks through this
 * boundary.
 */
export type TenantSummary = {
  name: string
  subdomain: string
  createdAt: string
  archived: boolean
  archivedAt: string | null
}

/** Generated tenant-admin credentials, included once in a successful createTenant response. */
export type TenantAdminCredentials = {
  email: string
  password: string
}

export type CreatedTenant = TenantSummary & { tenantAdmin: TenantAdminCredentials }

/** Payload shape both CreateTenantController and UpdateTenantController expect. */
export type TenantPayload = { name: string; slug: string }

/**
 * Payload shape CreateTenantController expects: `TenantPayload` plus the
 * tenant-admin's email (KOZ-27 rework: operator-supplied, no longer
 * auto-generated from the subdomain).
 */
export type CreateTenantPayload = TenantPayload & { adminEmail: string }

// Translated lazily (not module-level constants) so a language switch mid-
// session is reflected the next time one of these fires, not frozen at
// first import.
function tenantCreateFailedMessage(): string {
  return translate("tenants.error.createFailed", getClientLocale()) ?? "Failed to create tenant."
}
function tenantUpdateFailedMessage(): string {
  return translate("tenants.error.updateFailed", getClientLocale()) ?? "Failed to update tenant."
}
function tenantArchiveFailedMessage(): string {
  return translate("tenants.error.archiveFailed", getClientLocale()) ?? "Failed to archive tenant."
}
function tenantUnarchiveFailedMessage(): string {
  return translate("tenants.error.unarchiveFailed", getClientLocale()) ?? "Failed to unarchive tenant."
}

/**
 * Tenant overview: GET /api/admin/tenants, guarded backend-side by the
 * `tenant:list` permission (see ListTenantsController). Defaults to active
 * tenants only; pass `includeArchived: true` for the archived-only view
 * behind the overview's "show archived" toggle. Throws on a non-OK
 * response so the calling page can render an error state rather than
 * silently showing an empty table.
 */
export async function listTenants(includeArchived = false): Promise<TenantSummary[]> {
  const query = includeArchived ? "?archived=true" : ""
  return getJsonOrThrow(`/api/admin/tenants${query}`, "tenants")
}

/**
 * A single tenant's read model: GET /api/admin/tenants returns every
 * tenant, so the detail page finds "its" tenant client-side rather than
 * needing a dedicated GET-by-subdomain endpoint. Searches both the active
 * and archived views so an archived tenant's detail page still resolves.
 */
export async function getTenant(subdomain: string): Promise<TenantSummary | null> {
  const [active, archived] = await Promise.all([listTenants(false), listTenants(true)])
  const tenants = [...active, ...archived]

  return tenants.find((tenant) => tenant.subdomain === subdomain) ?? null
}

/**
 * Tenant creation: POST /api/admin/tenants, guarded backend-side by the
 * `tenant:create` permission (see CreateTenantController). Automatically
 * creates a tenant-admin account server-side; its generated credentials
 * come back in `data.tenantAdmin`. The backend reports failures (invalid
 * name/slug, subdomain already in use) as a single `{ message }`, not a
 * field-keyed map — attached to `slug` here (the field the backend
 * actually validates against) so `<ConfigForm>` shows it in the right
 * place.
 */
export async function createTenant(payload: CreateTenantPayload): Promise<ActionResult<CreatedTenant>> {
  return requestAction("/api/admin/tenants", {
    json: payload,
    fallbackMessage: tenantCreateFailedMessage,
    errorField: "slug",
  })
}

/**
 * Tenant edit: PATCH /api/admin/tenants/{subdomain}, guarded backend-side
 * by the `tenant:update` permission (see UpdateTenantController). Same
 * message -> `slug` field-error mapping as `createTenant`.
 */
export async function updateTenant(
  currentSubdomain: string,
  payload: TenantPayload
): Promise<ActionResult<TenantSummary>> {
  return requestAction(`/api/admin/tenants/${encodeURIComponent(currentSubdomain)}`, {
    method: "PATCH",
    json: payload,
    fallbackMessage: tenantUpdateFailedMessage,
    errorField: "slug",
  })
}

/**
 * Archives (soft-deletes) a tenant: POST /api/admin/tenants/{subdomain}/archive,
 * guarded backend-side by the `tenant:archive` permission (see
 * ArchiveTenantController). Called only after the caller has already shown
 * a confirmation dialog — this function performs the action immediately.
 */
export async function archiveTenant(subdomain: string): Promise<ActionResult<TenantSummary>> {
  return requestAction(`/api/admin/tenants/${encodeURIComponent(subdomain)}/archive`, {
    fallbackMessage: tenantArchiveFailedMessage,
  })
}

/**
 * Reverses `archiveTenant`: POST /api/admin/tenants/{subdomain}/unarchive,
 * guarded backend-side by the `tenant:archive` permission (see
 * UnarchiveTenantController).
 */
export async function unarchiveTenant(subdomain: string): Promise<ActionResult<TenantSummary>> {
  return requestAction(`/api/admin/tenants/${encodeURIComponent(subdomain)}/unarchive`, {
    fallbackMessage: tenantUnarchiveFailedMessage,
  })
}
