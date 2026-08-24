import { cookies } from "next/headers"

import { DEFAULT_LOCALE, LOCALE_COOKIE_NAME, isSupportedLocale, type Locale } from "@/lib/i18n/locale"

/**
 * Server-only counterpart to `getClientLocale` (lib/i18n/locale.ts), for the
 * handful of Server Components that render user-facing text directly
 * (app/(app)/page.tsx, app/(app)/not-found.tsx) instead of going through
 * `useTranslation()` in a client component. Kept in its own module — not
 * added to lib/i18n/locale.ts — because `next/headers` is server-only and
 * would break that file for the client components that already import it
 * (components/providers/i18n-provider.tsx, components/nav-user.tsx).
 *
 * Reads the same `kozijnr_locale` cookie the root layout reads to seed
 * `<I18nProvider>`, so a Server Component's own text renders in the same
 * language as the client-rendered UI around it.
 */
export async function getServerLocale(): Promise<Locale> {
  const cookieStore = await cookies()
  const cookieLocale = cookieStore.get(LOCALE_COOKIE_NAME)?.value
  return isSupportedLocale(cookieLocale) ? cookieLocale : DEFAULT_LOCALE
}
