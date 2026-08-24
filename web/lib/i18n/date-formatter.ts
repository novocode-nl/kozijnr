import type { Locale } from "@/lib/i18n/locale"

/**
 * KOZ-29 rework: date/time formatting used to be hardcoded to `"nl-NL"`
 * regardless of the active language. Maps the app's own two-letter
 * `Locale` to the `Intl` locale tag whose date/time conventions match it,
 * so a date follows whichever language the visitor picked (see
 * lib/i18n/locale.ts), not a fixed one.
 */
const INTL_LOCALE: Record<Locale, string> = {
  nl: "nl-NL",
  en: "en-GB",
}

export function dateTimeFormatter(locale: string): Intl.DateTimeFormat {
  const intlLocale = INTL_LOCALE[locale as Locale] ?? INTL_LOCALE.en
  return new Intl.DateTimeFormat(intlLocale, { dateStyle: "medium", timeStyle: "short" })
}
