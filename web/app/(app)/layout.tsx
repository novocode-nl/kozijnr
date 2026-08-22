import type { ReactNode } from "react"

import { AppShell } from "@/components/app-shell"
import { getAppContext } from "@/lib/context/app-context"

/**
 * Shared layout for every authenticated area. The Host subdomain (admin.*
 * vs. a tenant subdomain) decides which menu/content renders, all through
 * one shell component, so there's no separate layout to drift out of sync.
 */
export default async function AppLayout({
  children,
}: {
  children: ReactNode
}) {
  const context = await getAppContext()

  return <AppShell context={context}>{children}</AppShell>
}
