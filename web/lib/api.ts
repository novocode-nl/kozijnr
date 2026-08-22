import { apiBaseUrl } from "@/lib/api-base-url"
import type { LoginFormValues } from "@/lib/schemas/login"

/**
 * Browser-side service layer over the REST API on api.<base>. Components
 * call these functions; nothing else in the frontend talks to the backend.
 *
 * Every call is cross-origin (admin.<base> / <tenant>.<base> -> api.<base>)
 * but same-site, so `credentials: "include"` carries the HttpOnly
 * session/token cookies the API sets and the API's CorsListener allows the
 * origin through. Which tenant/admin context a call belongs to is derived
 * by the API from the browser-set Origin header.
 */
export const GENERIC_LOGIN_ERROR = "Invalid credentials."

export type LoginResult = { success: true } | { success: false; message: string }

/**
 * Read-model shape returned by GET /api/admin/tenants (KOZ-8's
 * TenantSummary::toArray) — subdomain + createdAt only, nothing from the
 * tenant's Postgres schema internals leaks through this boundary.
 */
export type TenantSummary = {
  subdomain: string
  createdAt: string
}

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
 * Tenant overview (KOZ-24): GET /api/admin/tenants, guarded backend-side by
 * the `tenant:list` permission (see ListTenantsController). Throws on a
 * non-OK response so the calling page can render an error state rather than
 * silently showing an empty table.
 */
export async function listTenants(): Promise<TenantSummary[]> {
  const response = await fetch(backendUrl("/api/admin/tenants"), {
    method: "GET",
    credentials: "include",
  })

  if (!response.ok) {
    throw new Error(`Failed to load tenants (status ${response.status}).`)
  }

  return response.json()
}

export async function adminLogout(): Promise<void> {
  await postBestEffort("/api/admin/logout")
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

// Logout is best effort: whatever the API answers, the caller navigates to
// /login next and the route guard takes over.
async function postBestEffort(path: string): Promise<void> {
  try {
    await fetch(backendUrl(path), { method: "POST", credentials: "include" })
  } catch {
    // ignore — see above
  }
}
