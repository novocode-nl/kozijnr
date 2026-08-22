import type { ReactNode } from "react"

import { AppShell } from "@/components/app-shell"
import { getAppContext } from "@/lib/context/app-context"

/**
 * Shared layout for every authenticated area. There is a single home route
 * (`/`) for both the tenant and admin contexts — the Host subdomain alone
 * (admin.* vs. any tenant subdomain) decides which menu/content renders,
 * all through this one route group and shell component, so there's no
 * separate layout to drift out of sync. It also backs the branded 404 and
 * catch-all, so an unmatched path for a logged-in visitor still renders
 * inside this same shell rather than Next's generic 404.
 *
 * `context` is resolved once, here, from the Host header (see
 * lib/context/app-context.ts) and passed down.
 */
export default async function AppLayout({
  children,
}: {
  children: ReactNode
}) {
  const context = await getAppContext()

  return <AppShell context={context}>{children}</AppShell>
}
