import { apiBaseUrl } from "@/lib/api-base-url"
import { getClientLocale } from "@/lib/i18n/locale"
import { translate } from "@/lib/i18n/translate"
import type { LoginFormValues } from "@/lib/schemas/login"
import type { ActionResult } from "@/lib/forms/types"

/**
 * Browser-side service layer over the REST API on api.<base>. Every call
 * is cross-origin but same-site, so `credentials: "include"` carries the
 * HttpOnly session/token cookies the API's CorsListener allows through.
 *
 * KOZ-29: the backend never translates anything itself — a failure response
 * carries an English `message` (log-friendly fallback) plus a stable,
 * machine-readable `errorKey` (e.g. "tenants.error.subdomainAlreadyExists",
 * matching a key in lib/i18n/resources/{nl,en}.json) and optional
 * `errorKeyParams` for interpolation. `apiErrorMessage` below is the one
 * place that turns that into UI text, in whichever language the visitor has
 * chosen (lib/i18n/locale.ts) — falling back to the backend's English
 * `message` if the key isn't in the catalog, so an unmapped backend error
 * still shows *something* instead of a blank string.
 */
function apiErrorMessage(body: unknown, fallback: string): string {
  const errorKey = isRecord(body) && typeof body.errorKey === "string" ? body.errorKey : null
  const params = isRecord(body) && isRecord(body.errorKeyParams) ? (body.errorKeyParams as Record<string, string | number>) : undefined

  if (errorKey) {
    const translated = translate(errorKey, getClientLocale(), params)
    if (translated !== undefined) {
      return translated
    }
  }

  return isRecord(body) && typeof body.message === "string" ? body.message : fallback
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null
}

/** Ultimate fallback if even the English catalog is somehow missing the key. */
const GENERIC_LOGIN_ERROR = "Invalid credentials."

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

// Translated lazily, same reasoning as tenantCreateFailedMessage() etc. below.
function tenantUserCreateFailedMessage(): string {
  return translate("tenants.error.createFailed", getClientLocale()) ?? "Failed to create tenant user."
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
  let response: Response
  try {
    response = await fetch(backendUrl(`/api/admin/tenants/${encodeURIComponent(subdomain)}/users`), {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })
  } catch {
    return { success: false, message: tenantUserCreateFailedMessage() }
  }

  if (!response.ok) {
    const body = await response.json().catch(() => null)
    const message = apiErrorMessage(body, tenantUserCreateFailedMessage())
    return { success: false, fieldErrors: { email: message } }
  }

  return { success: true, data: await response.json() }
}

export async function adminLogout(): Promise<void> {
  await postBestEffort("/api/admin/logout")
}

/** Response shape from GET /api/me: the currently authenticated tenant user. */
export type CurrentTenantUser = { email: string; roles: string[] }

/**
 * The logged-in tenant user, on their own tenant subdomain: GET /api/me
 * (see App\TenantUser\Infrastructure\Controller\MeController). Used by the
 * tenant-own "Gebruikers" page (KOZ-31 rework) to decide whether to show
 * the "Gebruiker toevoegen" action — ROLE_TENANT_ADMIN only, mirroring the
 * backend's own ROLE_TENANT_ADMIN gate on POST /api/users. Returns `null`
 * on any non-OK response rather than throwing, since a stale/expired
 * session here should just fall back to "not an admin" rather than crash
 * the page.
 */
export async function getMe(): Promise<CurrentTenantUser | null> {
  const response = await fetch(backendUrl("/api/me"), {
    method: "GET",
    credentials: "include",
  })

  if (!response.ok) {
    return null
  }

  return response.json()
}

/**
 * Tenant-own self-service users list (KOZ-31 rework): GET /api/users,
 * reachable by any authenticated tenant user on their own tenant
 * subdomain (see App\TenantUser\Infrastructure\Controller\ListOwnTenantUsersController).
 * Unlike `listTenantUsers`, takes no subdomain — the tenant is decided by
 * whichever subdomain the request itself is on.
 */
export async function listOwnTenantUsers(): Promise<TenantUserSummary[]> {
  const response = await fetch(backendUrl("/api/users"), {
    method: "GET",
    credentials: "include",
  })

  if (!response.ok) {
    throw new Error(`Failed to load tenant users (status ${response.status}).`)
  }

  return response.json()
}

/**
 * "Gebruiker toevoegen" action on the tenant-own "Gebruikers" page (KOZ-31
 * rework): POST /api/users, guarded backend-side by the ROLE_TENANT_ADMIN
 * role (see CreateOwnTenantUserController). Mirrors `createTenantUser`'s
 * error-mapping (backend reports a single `{ message, errorKey }`,
 * attached to `email` here) but never takes a subdomain — the tenant
 * context comes exclusively from the logged-in session's own subdomain.
 */
export async function createOwnTenantUser(
  payload: CreateTenantUserPayload
): Promise<ActionResult<CreatedTenantUser>> {
  let response: Response
  try {
    response = await fetch(backendUrl("/api/users"), {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })
  } catch {
    return { success: false, message: tenantUserCreateFailedMessage() }
  }

  if (!response.ok) {
    const body = await response.json().catch(() => null)
    const message = apiErrorMessage(body, tenantUserCreateFailedMessage())
    return { success: false, fieldErrors: { email: message } }
  }

  return { success: true, data: await response.json() }
}

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
    tenantCreateFailedMessage()
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
    tenantUpdateFailedMessage()
  )
}

/**
 * Archives (soft-deletes) a tenant: POST /api/admin/tenants/{subdomain}/archive,
 * guarded backend-side by the `tenant:archive` permission (see
 * ArchiveTenantController). Called only after the caller has already shown
 * a confirmation dialog — this function performs the action immediately.
 */
export async function archiveTenant(subdomain: string): Promise<ActionResult<TenantSummary>> {
  return postTenantAction(`/api/admin/tenants/${encodeURIComponent(subdomain)}/archive`, tenantArchiveFailedMessage())
}

/**
 * Reverses `archiveTenant`: POST /api/admin/tenants/{subdomain}/unarchive,
 * guarded backend-side by the `tenant:archive` permission (see
 * UnarchiveTenantController).
 */
export async function unarchiveTenant(subdomain: string): Promise<ActionResult<TenantSummary>> {
  return postTenantAction(
    `/api/admin/tenants/${encodeURIComponent(subdomain)}/unarchive`,
    tenantUnarchiveFailedMessage()
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
    const message = apiErrorMessage(body, networkErrorMessage)
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
    const message = apiErrorMessage(body, networkErrorMessage)
    return { success: false, fieldErrors: { slug: message } }
  }

  return { success: true, data: await response.json() }
}

function backendUrl(path: string): string {
  return `${apiBaseUrl(window.location.host, window.location.protocol)}${path}`
}

async function postCredentials(path: string, values: LoginFormValues): Promise<LoginResult> {
  const genericMessage = translate("auth.error.invalidCredentials", getClientLocale()) ?? GENERIC_LOGIN_ERROR

  let response: Response
  try {
    response = await fetch(backendUrl(path), {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(values),
    })
  } catch {
    return { success: false, message: genericMessage }
  }

  if (!response.ok) {
    const body = await response.json().catch(() => null)
    return { success: false, message: apiErrorMessage(body, genericMessage) }
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
