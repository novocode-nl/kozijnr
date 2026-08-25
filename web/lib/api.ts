import { apiBaseUrl } from "@/lib/api-base-url"
import { DEFAULT_LOCALE, getClientLocale, isSupportedLocale, type Locale } from "@/lib/i18n/locale"
import { translate } from "@/lib/i18n/translate"
import type { LoginFormValues } from "@/lib/schemas/login"
import type { TenantLocaleFormValues } from "@/lib/schemas/tenant-settings"
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

/**
 * Read-model shape returned by GET /api/admin/users and the create endpoint
 * (UserSummary::toArray) — the admin user overview (KOZ-30).
 */
export type AdminUserSummary = {
  email: string
  roles: string[]
}

/** Generated admin-user credentials, included once in a successful createAdminUser response. */
export type AdminUserCredentials = {
  email: string
  password: string
}

export type CreatedAdminUser = AdminUserSummary & { password: string }

/**
 * KOZ-34: the current tenant's default locale, returned alongside a
 * successful tenant login so the frontend can switch to it immediately
 * (LoginForm) instead of whatever locale happened to be showing on the
 * login screen — every new login starts in the tenant's default locale,
 * there is no per-user override that persists across sessions (ticket
 * scope).
 */
export type TenantLoginResult =
  | { success: true; data: { defaultLocale: Locale } }
  | { success: false; message: string }

export async function login(values: LoginFormValues): Promise<TenantLoginResult> {
  const genericMessage = translate("auth.error.invalidCredentials", getClientLocale()) ?? GENERIC_LOGIN_ERROR

  let response: Response
  try {
    response = await fetch(backendUrl("/api/login"), {
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

  const body = await response.json().catch(() => null)
  const rawDefaultLocale = isRecord(body) && typeof body.defaultLocale === "string" ? body.defaultLocale : null
  const defaultLocale = isSupportedLocale(rawDefaultLocale) ? rawDefaultLocale : DEFAULT_LOCALE

  return { success: true, data: { defaultLocale } }
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
 * Admin user overview (KOZ-30): GET /api/admin/users, guarded backend-side
 * by the `user:list` permission (see ListAdminUsersController).
 */
export async function listAdminUsers(): Promise<AdminUserSummary[]> {
  const response = await fetch(backendUrl("/api/admin/users"), {
    method: "GET",
    credentials: "include",
  })

  if (!response.ok) {
    throw new Error(`Failed to load admin users (status ${response.status}).`)
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

/** Payload shape CreateAdminUserController expects. */
export type CreateAdminUserPayload = { email: string }

function adminUserCreateFailedMessage(): string {
  return translate("users.error.createFailed", getClientLocale()) ?? "Failed to create admin user."
}

/**
 * Admin user creation (KOZ-30): POST /api/admin/users, guarded backend-side
 * by the `user:create` permission (see CreateAdminUserController). Every
 * user created this way gets ROLE_SUPER_ADMIN and a generated password,
 * returned once in `data.password` — same one-time-credentials pattern as
 * `createTenant`'s `tenantAdmin` block. Failures (invalid/duplicate email)
 * are attached to the `email` field so `<ConfigForm>` shows them in the
 * right place.
 */
export async function createAdminUser(payload: CreateAdminUserPayload): Promise<ActionResult<CreatedAdminUser>> {
  let response: Response
  try {
    response = await fetch(backendUrl("/api/admin/users"), {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })
  } catch {
    return { success: false, message: adminUserCreateFailedMessage() }
  }

  if (!response.ok) {
    const body = await response.json().catch(() => null)
    const message = apiErrorMessage(body, adminUserCreateFailedMessage())
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

/**
 * KOZ-34: the current tenant's settings (default locale + whether a login
 * image has been uploaded), as returned by GET /api/settings — guarded
 * backend-side by ROLE_TENANT_ADMIN (see GetTenantSettingsController).
 */
export type TenantSettings = { defaultLocale: Locale; hasLoginImage: boolean }

function tenantSettingsSaveFailedMessage(): string {
  return translate("tenantSettings.error.saveFailed", getClientLocale()) ?? "Failed to save settings."
}

/**
 * Tenant settings page (KOZ-34): GET /api/settings. Throws on a non-OK
 * response, same convention as `listTenants`/`listTenantUsers` — the
 * calling page renders an error state rather than a silently empty form.
 */
export async function getTenantSettings(): Promise<TenantSettings> {
  const response = await fetch(backendUrl("/api/settings"), {
    method: "GET",
    credentials: "include",
  })

  if (!response.ok) {
    throw new Error(`Failed to load tenant settings (status ${response.status}).`)
  }

  return response.json()
}

/**
 * Tenant settings page "default language" section (KOZ-34): PATCH
 * /api/settings/locale, guarded backend-side by ROLE_TENANT_ADMIN. Backend
 * failures (unsupported locale) come back as a single `{ message, errorKey }`,
 * attached to `defaultLocale` here so `<ConfigForm>` shows it in the right
 * place.
 */
export async function updateTenantDefaultLocale(
  values: TenantLocaleFormValues
): Promise<ActionResult<TenantSettings>> {
  let response: Response
  try {
    response = await fetch(backendUrl("/api/settings/locale"), {
      method: "PATCH",
      credentials: "include",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(values),
    })
  } catch {
    return { success: false, message: tenantSettingsSaveFailedMessage() }
  }

  if (!response.ok) {
    const body = await response.json().catch(() => null)
    const message = apiErrorMessage(body, tenantSettingsSaveFailedMessage())
    return { success: false, fieldErrors: { defaultLocale: message } }
  }

  return { success: true, data: await response.json() }
}

/**
 * Tenant settings page "login image" section (KOZ-34): POST
 * /api/settings/login-image (multipart/form-data, field name "image"),
 * guarded backend-side by ROLE_TENANT_ADMIN (see
 * UploadTenantLoginImageController). Mirrors the upload-failure mapping the
 * other tenant-settings actions use, attached to a `loginImage`
 * pseudo-field since this isn't driven by `<ConfigForm>`.
 */
export async function uploadTenantLoginImage(file: File): Promise<ActionResult<TenantSettings>> {
  const formData = new FormData()
  formData.append("image", file)

  let response: Response
  try {
    response = await fetch(backendUrl("/api/settings/login-image"), {
      method: "POST",
      credentials: "include",
      body: formData,
    })
  } catch {
    return { success: false, message: tenantSettingsSaveFailedMessage() }
  }

  if (!response.ok) {
    const body = await response.json().catch(() => null)
    const message = apiErrorMessage(body, tenantSettingsSaveFailedMessage())
    return { success: false, fieldErrors: { loginImage: message } }
  }

  return { success: true, data: await response.json() }
}

/**
 * Fetches the current tenant's login-screen image (KOZ-34) and returns a
 * local object URL for it, or `null` when none has been uploaded (404) or
 * the request otherwise fails — see GetTenantLoginImageController, public,
 * no authenticated session required.
 *
 * Deliberately `fetch()` (a real CORS request, always sending an `Origin`
 * header), never a plain `<img src="…api.<base>/api/login-image">`: this
 * app's tenant resolution for the api.<base> hostname
 * (App\Tenancy\Infrastructure\TenantResolverListener::subdomainFromOrigin)
 * depends on that `Origin` header to know which tenant to resolve, and
 * browsers omit it for a plain same-site `<img>` load — verified by hand
 * while building this feature: without this, Chrome's ORB (Opaque Response
 * Blocking) blocks the image outright (net::ERR_BLOCKED_BY_ORB) and, even
 * disregarding that, the backend has no Origin to resolve the tenant from
 * and 404s. `fetch()` doesn't have either problem: it always sends Origin
 * (so CorsListener answers with the right Access-Control-* headers and
 * TenantResolverListener resolves the right tenant), and reading its body
 * as a blob is a real (non-opaque) CORS response, immune to ORB.
 *
 * Caller owns the returned URL's lifetime — revoke it with
 * `URL.revokeObjectURL()` once no longer displayed (e.g. in a `useEffect`
 * cleanup) to avoid leaking blob memory.
 */
export async function fetchTenantLoginImageUrl(): Promise<string | null> {
  const url = `${apiBaseUrl(window.location.host, window.location.protocol)}/api/login-image`

  let response: Response
  try {
    response = await fetch(url)
  } catch {
    return null
  }

  if (!response.ok) {
    return null
  }

  const blob = await response.blob()
  return URL.createObjectURL(blob)
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
