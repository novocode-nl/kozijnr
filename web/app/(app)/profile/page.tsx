import { notFound } from "next/navigation"

import { getAppContext } from "@/lib/context/app-context"

import TenantProfilePage from "./tenant-profile-page"

/**
 * KOZ-33: the tenant-user profile page. Admin-side has no equivalent yet
 * (explicitly out of scope for this ticket — "Profielpagina voor
 * admin-gebruikers (apart ticket indien gewenst)"), so the admin subdomain
 * gets a regular 404 here rather than a route that silently does nothing,
 * mirroring how `[...notFound]` renders the shared 404 for any other
 * unmatched path.
 */
export default async function ProfilePage() {
  const context = await getAppContext()

  if (context === "admin") {
    notFound()
  }

  return <TenantProfilePage />
}
