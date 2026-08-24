import en from "@/lib/i18n/resources/en.json"
import nl from "@/lib/i18n/resources/nl.json"
import { DEFAULT_LOCALE, type Locale } from "@/lib/i18n/locale"

/**
 * The resource catalogs, nested by domain (`common`, `tenants.error`,
 * `auth.error`, ...) — the same shape react-i18next's `useTranslation()`
 * reads via its default dot-notation key resolution (see
 * components/providers/i18n-provider.tsx).
 */
export const resources: Record<Locale, Record<string, unknown>> = { en, nl }

/**
 * Flattened dot-path -> string lookup built from `resources`, so
 * `lib/api.ts` can translate a backend `errorKey` (itself a dot path, e.g.
 * "tenants.error.subdomainAlreadyExists") without needing React or an
 * i18next instance — it runs from plain event-handler code, not a
 * component.
 */
function flatten(source: Record<string, unknown>, prefix = "", out: Record<string, string> = {}): Record<string, string> {
  for (const [key, value] of Object.entries(source)) {
    const path = prefix ? `${prefix}.${key}` : key
    if (typeof value === "string") {
      out[path] = value
    } else if (value && typeof value === "object") {
      flatten(value as Record<string, unknown>, path, out)
    }
  }
  return out
}

const flatCatalogs: Record<Locale, Record<string, string>> = {
  en: flatten(en),
  nl: flatten(nl),
}

/**
 * Looks up `key` (a dot path into the resource catalog) in the given
 * locale's flattened catalog (falling back to English, then to `undefined`
 * if the key doesn't exist anywhere), interpolating any `{{name}}`
 * placeholders from `params`. Kept dependency-free and pure so it's
 * trivially unit-testable — see lib/i18n/translate.test.ts for the
 * NL-vs-EN proof this ticket's DoD calls for.
 */
export function translate(
  key: string,
  locale: Locale = DEFAULT_LOCALE,
  params?: Record<string, string | number>
): string | undefined {
  const template = flatCatalogs[locale]?.[key] ?? flatCatalogs.en[key]

  if (template === undefined) {
    return undefined
  }

  if (!params) {
    return template
  }

  return template.replace(/\{\{(\w+)\}\}/g, (match, name: string) =>
    name in params ? String(params[name]) : match
  )
}

/**
 * Non-optional counterpart to `translate`, for call sites that always pass
 * a known-good key (e.g. the Zod schema factories in lib/schemas/) and want
 * a plain `string` rather than `string | undefined` — falls back to the key
 * itself (still better than `undefined` reaching, say, a Zod issue message)
 * if it's ever missing from the catalog.
 */
export function translateRequired(
  key: string,
  locale: Locale = DEFAULT_LOCALE,
  params?: Record<string, string | number>
): string {
  return translate(key, locale, params) ?? key
}
