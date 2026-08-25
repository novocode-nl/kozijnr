import { DEFAULT_LOCALE, getClientLocale, isSupportedLocale, type Locale } from "@/lib/i18n/locale"
import { translate } from "@/lib/i18n/translate"
import type { LoginFormValues } from "@/lib/schemas/login"

import { apiErrorMessage, backendUrl, isRecord } from "./http"

/** Ultimate fallback if even the English catalog is somehow missing the key. */
const GENERIC_LOGIN_ERROR = "Invalid credentials."

export type LoginResult = { success: true } | { success: false; message: string }

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
