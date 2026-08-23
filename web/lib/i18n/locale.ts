/**
 * KOZ-29: the two languages the UI offers, and the cookie that persists a
 * visitor's choice between sessions. A cookie (not localStorage) so a
 * Server Component (the root layout) can read it before first paint and
 * render `<html lang>` / the i18n provider's initial language correctly,
 * avoiding a flash of the wrong language on load.
 */
export const SUPPORTED_LOCALES = ["nl", "en"] as const

export type Locale = (typeof SUPPORTED_LOCALES)[number]

export const DEFAULT_LOCALE: Locale = "nl"

export const LOCALE_COOKIE_NAME = "kozijnr_locale"

export function isSupportedLocale(value: string | null | undefined): value is Locale {
  return value != null && (SUPPORTED_LOCALES as readonly string[]).includes(value)
}

/**
 * Reads the persisted locale from the browser's own cookie jar. Browser-only
 * (`lib/api.ts`'s error-translation path calls this from event handlers,
 * never during server rendering) — falls back to `DEFAULT_LOCALE` anywhere
 * `document` doesn't exist, e.g. in a non-DOM test environment.
 */
export function getClientLocale(): Locale {
  if (typeof document === "undefined") {
    return DEFAULT_LOCALE
  }

  const match = document.cookie.match(new RegExp(`(?:^|; )${LOCALE_COOKIE_NAME}=([^;]*)`))
  const value = match ? decodeURIComponent(match[1]) : null

  return isSupportedLocale(value) ? value : DEFAULT_LOCALE
}

/** Persists the chosen locale for one year, readable by both client and server. */
export function setClientLocale(locale: Locale): void {
  if (typeof document === "undefined") {
    return
  }

  document.cookie = `${LOCALE_COOKIE_NAME}=${locale}; path=/; max-age=31536000; samesite=lax`
}
