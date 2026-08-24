import Link from "next/link"

import { Button } from "@/components/ui/button"
import { getAppContext } from "@/lib/context/app-context"
import { getServerLocale } from "@/lib/i18n/server-locale"
import { translate } from "@/lib/i18n/translate"

/**
 * Branded 404, scoped to the (app) route group so it renders inside the
 * shared AppShell (app/(app)/layout.tsx) — sidebar/nav and all — instead of
 * Next's generic, unstyled 404. Only reached by a visitor with a
 * valid session: proxy.ts already redirects anyone without one straight to
 * /login before Next's routing (and therefore this boundary) ever runs, so
 * by the time this renders we already know the visitor is logged in.
 *
 * Triggered by the catch-all app/(app)/[...notFound]/page.tsx, which is
 * what makes Next resolve *any* unmatched path to this nested boundary
 * rather than falling back to a root-level 404 outside the layout.
 */
export default async function NotFound() {
  const [context, locale] = await Promise.all([getAppContext(), getServerLocale()])
  const body = translate(context === "admin" ? "notFound.bodyAdmin" : "notFound.bodyTenant", locale)

  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-4 rounded-xl bg-muted/50 p-6 text-center">
      <p className="text-sm font-medium text-muted-foreground">404</p>
      <h1 className="text-2xl font-bold">{translate("notFound.title", locale)}</h1>
      <p className="max-w-sm text-muted-foreground">{body}</p>
      <Button nativeButton={false} render={<Link href="/" />}>
        {translate("notFound.backHome", locale)}
      </Button>
    </div>
  )
}
