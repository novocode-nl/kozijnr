import { notFound } from "next/navigation"

import { getAppContext } from "@/lib/context/app-context"

import TenantSettingsPage from "./tenant-settings-page"

/**
 * `/settings` (KOZ-34): tenant-only for now — a tenant-admin manages their
 * own tenant's login image and default locale here. The admin realm has no
 * equivalent settings concept yet (its "Instellingen" secondary-nav item
 * still points at the "#" placeholder — see lib/navigation/menu-config.ts),
 * so an admin.<domein> visit to this path 404s via the branded 404
 * boundary, same as any other genuinely unmatched route
 * (app/(app)/not-found.tsx).
 */
export default async function SettingsPage() {
  const context = await getAppContext()

  if (context !== "tenant") {
    notFound()
  }

  return <TenantSettingsPage />
}
