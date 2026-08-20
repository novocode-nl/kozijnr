import type { ReactNode } from "react"

import { AppShell } from "@/components/app-shell"
import { getAppContext } from "@/lib/context/app-context"

/**
 * Shared layout for every authenticated area of the app (KOZ-14): both
 * `/dashboard` (tenant) and `/admin` (admin) render through this route
 * group so they get the exact same sidebar shell component
 * (components/app-shell.tsx), just with a different `context` — never two
 * separate layout implementations that could drift apart.
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
