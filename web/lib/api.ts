import { apiBaseUrl } from "@/lib/api-base-url"
import type { LoginFormValues } from "@/lib/schemas/login"
import type { ActionResult } from "@/lib/forms/types"

/**
 * Browser-side service layer over the REST API on api.<base>. Every call
 * is cross-origin but same-site, so `credentials: "include"` carries the
 * HttpOnly session/token cookies the API's CorsListener allows through.
 */
export const GENERIC_LOGIN_ERROR = "Invalid credentials."

export type LoginResult = { success: true } | { success: false; message: string }

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

/** One tenant user, as returned by GET /api/admin/tenants/{subdomain}/users. */
export type TenantUserSummary = {
  email: string
  roles: string[]
}

/** Generated tenant-admin credentials, included once in a successful createTenant response. */
export type TenantAdminCredentials = {
  email: string
  password: string
}

export type CreatedTenant = TenantSummary & { tenantAdmin: TenantAdminCredentials }

export async function login(values: LoginFormValues): Promise<LoginResult> {
  return postCredentials("/api/login", values)
}

export async function adminLogin(values: LoginFormValues): Promise<LoginResult> {
  return postCredentials("/api/admin/login", values)
}

export async function logout(): Promise<void> {
  await postBestEffort("/api/logout")
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
  const response = await fetch(backendUrl(`/api/admin/tenants${query}`), {
    method: "GET",
    credentials: "include",
  })

  if (!response.ok) {
    throw new Error(`Failed to load tenants (status ${response.status}).`)
  }

  return response.json()
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
 * Tenant users tab: GET /api/admin/tenants/{subdomain}/users, guarded
 * backend-side by the `tenant:users:list` permission (see
 * ListTenantUsersController).
 */
export async function listTenantUsers(subdomain: string): Promise<TenantUserSummary[]> {
  const response = await fetch(backendUrl(`/api/admin/tenants/${encodeURIComponent(subdomain)}/users`), {
    method: "GET",
    credentials: "include",
  })

  if (!response.ok) {
    throw new Error(`Failed to load tenant users (status ${response.status}).`)
  }

  return response.json()
}

export async function adminLogout(): Promise<void> {
  await postBestEffort("/api/admin/logout")
}

/** Payload shape both CreateTenantController and UpdateTenantController expect. */
export type TenantPayload = { name: string; slug: string }

/**
 * Payload shape CreateTenantController expects: `TenantPayload` plus the
 * tenant-admin's email (KOZ-27 rework: operator-supplied, no longer
 * auto-generated from the subdomain).
 */
export type CreateTenantPayload = TenantPayload & { adminEmail: string }

const TENANT_CREATE_FAILED_MESSAGE = "Kon de tenant niet aanmaken. Probeer het opnieuw."
const TENANT_UPDATE_FAILED_MESSAGE = "Kon de tenant niet bijwerken. Probeer het opnieuw."
const TENANT_ARCHIVE_FAILED_MESSAGE = "Kon de tenant niet archiveren. Probeer het opnieuw."
const TENANT_UNARCHIVE_FAILED_MESSAGE = "Kon de tenant niet dearchiveren. Probeer het opnieuw."

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
  return submitTenantPayload<CreatedTenant, CreateTenantPayload>(
    "/api/admin/tenants",
    "POST",
    payload,
    TENANT_CREATE_FAILED_MESSAGE
  )
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
  return submitTenantPayload<TenantSummary>(
    `/api/admin/tenants/${encodeURIComponent(currentSubdomain)}`,
    "PATCH",
    payload,
    TENANT_UPDATE_FAILED_MESSAGE
  )
}

/**
 * Archives (soft-deletes) a tenant: POST /api/admin/tenants/{subdomain}/archive,
 * guarded backend-side by the `tenant:archive` permission (see
 * ArchiveTenantController). Called only after the caller has already shown
 * a confirmation dialog — this function performs the action immediately.
 */
export async function archiveTenant(subdomain: string): Promise<ActionResult<TenantSummary>> {
  return postTenantAction(`/api/admin/tenants/${encodeURIComponent(subdomain)}/archive`, TENANT_ARCHIVE_FAILED_MESSAGE)
}

/**
 * Reverses `archiveTenant`: POST /api/admin/tenants/{subdomain}/unarchive,
 * guarded backend-side by the `tenant:archive` permission (see
 * UnarchiveTenantController).
 */
export async function unarchiveTenant(subdomain: string): Promise<ActionResult<TenantSummary>> {
  return postTenantAction(
    `/api/admin/tenants/${encodeURIComponent(subdomain)}/unarchive`,
    TENANT_UNARCHIVE_FAILED_MESSAGE
  )
}

async function postTenantAction(path: string, networkErrorMessage: string): Promise<ActionResult<TenantSummary>> {
  let response: Response
  try {
    response = await fetch(backendUrl(path), { method: "POST", credentials: "include" })
  } catch {
    return { success: false, message: networkErrorMessage }
  }

  if (!response.ok) {
    const body = await response.json().catch(() => null)
    const message = typeof body?.message === "string" ? body.message : networkErrorMessage
    return { success: false, message }
  }

  return { success: true, data: await response.json() }
}

async function submitTenantPayload<TResult, TPayload extends TenantPayload = TenantPayload>(
  path: string,
  method: "POST" | "PATCH",
  payload: TPayload,
  networkErrorMessage: string
): Promise<ActionResult<TResult>> {
  let response: Response
  try {
    response = await fetch(backendUrl(path), {
      method,
      credentials: "include",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })
  } catch {
    return { success: false, message: networkErrorMessage }
  }

  if (!response.ok) {
    const body = await response.json().catch(() => null)
    const message = typeof body?.message === "string" ? body.message : networkErrorMessage
    return { success: false, fieldErrors: { slug: message } }
  }

  return { success: true, data: await response.json() }
}

function backendUrl(path: string): string {
  return `${apiBaseUrl(window.location.host, window.location.protocol)}${path}`
}

async function postCredentials(path: string, values: LoginFormValues): Promise<LoginResult> {
  let response: Response
  try {
    response = await fetch(backendUrl(path), {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(values),
    })
  } catch {
    return { success: false, message: GENERIC_LOGIN_ERROR }
  }

  if (!response.ok) {
    return { success: false, message: GENERIC_LOGIN_ERROR }
  }

  return { success: true }
}

// Logout is best effort: the caller navigates to /login regardless.
async function postBestEffort(path: string): Promise<void> {
  try {
    await fetch(backendUrl(path), { method: "POST", credentials: "include" })
  } catch {
    // ignore
  }
}
