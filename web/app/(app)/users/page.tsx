import { getAppContext } from "@/lib/context/app-context"

import AdminUsersPage from "./admin-users-page"
import OwnUsersPage from "./own-users-page"

/**
 * Single shared `/users` route for both contexts (KOZ-31 rework — KOZ-30's
 * admin user overview and KOZ-31's tenant self-service page both landed on
 * this same path). Reuses the Host-based context detection
 * (lib/context/app-context.ts) that already drives the sidebar menu and
 * the home page, so there is one route instead of two near-identical ones
 * fighting over the same URL.
 */
export default async function UsersPage() {
  const context = await getAppContext()

  return context === "admin" ? <AdminUsersPage /> : <OwnUsersPage />
}
