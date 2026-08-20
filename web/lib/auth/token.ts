/**
 * Bearer-token storage for the tenant-user session (KOZ-11 issues the
 * token, KOZ-13 is the first consumer). localStorage rather than a cookie:
 * this token is only ever attached manually as an `Authorization: Bearer`
 * header from client-side fetches (see lib/api.ts), never something the
 * browser needs to send automatically, so a cookie's extra
 * CSRF/SameSite-domain considerations buy nothing here.
 */
const STORAGE_KEY = "kozijnr_tenant_token"

export function storeToken(token: string): void {
  if (typeof window === "undefined") return
  window.localStorage.setItem(STORAGE_KEY, token)
}

export function getStoredToken(): string | null {
  if (typeof window === "undefined") return null
  return window.localStorage.getItem(STORAGE_KEY)
}

export function clearStoredToken(): void {
  if (typeof window === "undefined") return
  window.localStorage.removeItem(STORAGE_KEY)
}
