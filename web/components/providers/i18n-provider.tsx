"use client"

import { useState, type ReactNode } from "react"
import i18next, { type i18n as I18nInstance } from "i18next"
import { I18nextProvider, initReactI18next } from "react-i18next"

import { resources } from "@/lib/i18n/translate"
import type { Locale } from "@/lib/i18n/locale"

/**
 * KOZ-29: wraps the app in an i18next instance seeded with `initialLocale`
 * (read server-side from the locale cookie by the root layout — see
 * app/layout.tsx). Resource catalogs are nested by domain (`common`,
 * `form.error`, `auth.error`, ...) — i18next's default dot-notation key
 * resolution walks that nesting, so `t("form.error.tenantNotFound")`
 * resolves the same dotted string the backend sends back as `errorKey`
 * (see lib/i18n/translate.ts, which does the equivalent lookup outside
 * React for lib/api.ts).
 *
 * Deliberately a *fresh* instance per component-tree mount
 * (`useState(() => ...)`, never a module-level singleton): Next's App
 * Router renders client components on the server too, and a shared
 * `i18next` singleton mutated via `changeLanguage` would leak one visitor's
 * language into another visitor's concurrent request on the same server
 * process.
 */
export function I18nProvider({
  initialLocale,
  children,
}: {
  initialLocale: Locale
  children: ReactNode
}) {
  const [instance] = useState<I18nInstance>(() => {
    const i18n = i18next.createInstance()
    i18n.use(initReactI18next).init({
      resources: {
        nl: { translation: resources.nl },
        en: { translation: resources.en },
      },
      lng: initialLocale,
      fallbackLng: "en",
      interpolation: { escapeValue: false },
    })
    return i18n
  })

  return <I18nextProvider i18n={instance}>{children}</I18nextProvider>
}
