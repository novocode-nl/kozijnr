import type { ReactNode } from "react"

import { AppShell } from "@/components/app-shell"
import { getAppContext } from "@/lib/context/app-context"

/**
 * Shared layout for every authenticated area of the app (KOZ-14). There is
 * a single home route (`/`) for both the tenant and admin contexts —
 * KOZ-14's rework removed the separate `/admin` route the functional review
 * flagged, so it's the Host subdomain alone (admin.* vs. any tenant
 * subdomain) that decides which menu/content renders, all through this one
 * route group and the exact same sidebar shell component
 * (components/app-shell.tsx), just with a different `context` — never two
 * separate layout implementations that could drift apart.
 *
 * KOZ-21: `/dashboard` no longer exists as its own route — it always
 * redirects to `/` (proxy.ts), and this layout also backs the branded 404
 * (not-found.tsx) and catch-all ([...notFound]/page.tsx) alongside it, so
 * an unmatched path for a logged-in visitor still renders inside this same
 * shell rather than Next's generic 404.
 *
 * `context` is resolved once, here, from the request's Host header (see
 * lib/context/app-context.ts for why) and passed down — nothing below this
 * layout needs to know how that decision gets made.
 */
export default async function AppLayout({
  children,
}: {
  children: ReactNode
}) {
  const context = await getAppContext()

  return <AppShell context={context}>{children}</AppShell>
}
