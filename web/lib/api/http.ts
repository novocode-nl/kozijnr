import { apiBaseUrl } from "@/lib/api-base-url"
import { getClientLocale } from "@/lib/i18n/locale"
import { translate } from "@/lib/i18n/translate"
import type { ActionResult } from "@/lib/forms/types"

/**
 * Shared core of the browser-side service layer over the REST API on
 * api.<base>. Every call is cross-origin but same-site, so
 * `credentials: "include"` carries the HttpOnly session/token cookies the
 * API's CorsListener allows through.
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
export function apiErrorMessage(body: unknown, fallback: string): string {
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

export function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null
}

export function backendUrl(path: string): string {
  return `${apiBaseUrl(window.location.host, window.location.protocol)}${path}`
}

export type ActionRequestOptions = {
  method?: "POST" | "PATCH"
  json?: unknown
  formData?: FormData
  /** Lazily evaluated so a mid-session language switch is reflected. */
  fallbackMessage: () => string
  /**
   * When set, a non-OK response maps to { fieldErrors: { [errorField]:
   * message } } instead of a top-level { message } — the field every one of
   * that endpoint's failures is actually about, so `<ConfigForm>` shows the
   * error in the right place. A network error still maps to { message },
   * matching how every wrapper behaved before this core existed.
   */
  errorField?: string
}

/**
 * The one try-fetch-catch → !ok → apiErrorMessage → ActionResult flow every
 * mutating endpoint wrapper previously spelled out by hand. Passing `json`
 * sends a JSON body (with Content-Type); passing `formData` sends it as-is
 * (no manual Content-Type — the browser sets the multipart boundary).
 */
export async function requestAction<T>(path: string, options: ActionRequestOptions): Promise<ActionResult<T>> {
  const { method = "POST", json, formData, fallbackMessage, errorField } = options

  let response: Response
  try {
    response = await fetch(backendUrl(path), {
      method,
      credentials: "include",
      ...(json !== undefined
        ? { headers: { "Content-Type": "application/json" }, body: JSON.stringify(json) }
        : {}),
      ...(formData !== undefined ? { body: formData } : {}),
    })
  } catch {
    return { success: false, message: fallbackMessage() }
  }

  if (!response.ok) {
    const body = await response.json().catch(() => null)
    const message = apiErrorMessage(body, fallbackMessage())
    return errorField ? { success: false, fieldErrors: { [errorField]: message } } : { success: false, message }
  }

  return { success: true, data: await response.json() }
}

/**
 * GET that throws on a non-OK response, so the calling page can render an
 * error state rather than silently showing an empty table/form.
 */
export async function getJsonOrThrow<T>(path: string, what: string): Promise<T> {
  const response = await fetch(backendUrl(path), { method: "GET", credentials: "include" })

  if (!response.ok) {
    throw new Error(`Failed to load ${what} (status ${response.status}).`)
  }

  return response.json()
}
