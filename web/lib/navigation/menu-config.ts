import {
  Building2,
  LayoutDashboard,
  LifeBuoy,
  Settings,
  ShieldCheck,
  Users,
} from "lucide-react"
import type { LucideIcon } from "lucide-react"

import type { AppContext } from "@/lib/context/app-context"

/**
 * `title` is a translation key (see lib/i18n/resources/{nl,en}.json), not
 * display text — `<NavMain>`/`<NavSecondary>` translate it via
 * `useTranslation()` at render time (KOZ-29).
 */
export type NavMainItem = {
  title: string
  url: string
  icon: LucideIcon
  items?: { title: string; url: string }[]
}

export type NavSecondaryItem = {
  title: string
  url: string
  icon: LucideIcon
}

/**
 * The tenant menu: identical for every tenant, no per-tenant items.
 *
 * KOZ-31 rework: "Gebruikers" links to the tenant-own self-service
 * "Gebruiker toevoegen" page — reachable by any logged-in tenant user (the
 * page itself hides the add-action for anyone without ROLE_TENANT_ADMIN,
 * and the backend enforces the same gate independently, see
 * CreateOwnTenantUserController).
 */
const tenantNavMain: NavMainItem[] = [
  {
    title: "nav.dashboard",
    url: "/",
    icon: LayoutDashboard,
  },
  {
    title: "nav.users",
    url: "/users",
    icon: Users,
  },
]

/**
 * The admin menu: shown on the reserved `admin` subdomain. Placeholder
 * targets ("#") for items whose real pages don't exist yet. The admin
 * dashboard lives at the same path (`/`) as the tenant dashboard — the
 * Host subdomain alone decides which menu/content renders.
 */
const adminNavMain: NavMainItem[] = [
  {
    title: "nav.adminDashboard",
    url: "/",
    icon: LayoutDashboard,
  },
  {
    title: "nav.tenants",
    url: "/tenants",
    icon: Building2,
  },
  {
    title: "nav.users",
    url: "/users",
    icon: Users,
  },
]

/**
 * The tenant secondary menu: shown below the primary nav, at the bottom
 * of the sidebar. Kept separate from `tenantNavMain` so it can diverge
 * per environment without touching primary-nav rendering.
 */
const tenantNavSecondary: NavSecondaryItem[] = [
  {
    title: "nav.settings",
    // KOZ-34: tenant settings page (login image + default locale). The
    // page itself gates ROLE_TENANT_ADMIN-only content behind a
    // "geen toegang" state (see app/(app)/settings/tenant-settings-page.tsx)
    // rather than hiding this nav item for non-admins, mirroring how
    // "Gebruiker toevoegen" is hidden on the Gebruikers page instead of
    // the nav item itself.
    url: "/settings",
    icon: Settings,
  },
  {
    title: "nav.support",
    url: "#",
    icon: LifeBuoy,
  },
]

/** The admin secondary menu: same items as tenant today, configured separately. */
const adminNavSecondary: NavSecondaryItem[] = [
  {
    title: "nav.settings",
    url: "#",
    icon: Settings,
  },
  {
    title: "nav.support",
    url: "#",
    icon: LifeBuoy,
  },
]

export const contextLabel: Record<AppContext, string> = {
  admin: "Kozijnr Admin",
  tenant: "Kozijnr",
}

export const contextIcon: Record<AppContext, LucideIcon> = {
  admin: ShieldCheck,
  tenant: Building2,
}

/** Single source of truth for "which menu belongs to which context". */
export function getNavMainForContext(context: AppContext): NavMainItem[] {
  return context === "admin" ? adminNavMain : tenantNavMain
}

/**
 * Single source of truth for "which secondary menu belongs to which
 * context" — mirrors `getNavMainForContext` so admin and tenant can each
 * configure their own secondary nav (shown at the bottom of the sidebar)
 * independently.
 */
export function getNavSecondaryForContext(context: AppContext): NavSecondaryItem[] {
  return context === "admin" ? adminNavSecondary : tenantNavSecondary
}
