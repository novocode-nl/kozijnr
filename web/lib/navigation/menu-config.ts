import {
  Building2,
  LayoutDashboard,
  Settings,
  ShieldCheck,
  Users,
} from "lucide-react"
import type { LucideIcon } from "lucide-react"

import type { AppContext } from "@/lib/context/app-context"

export type NavMainItem = {
  title: string
  url: string
  icon: LucideIcon
  items?: { title: string; url: string }[]
}

/**
 * The tenant menu: identical for every tenant (no per-tenant hardcoded
 * items) — the only variation this ticket introduces is admin vs. tenant.
 * Per-item filtering by role/permission is explicitly out of scope (KOZ-14
 * DoD) — a future ticket wires this up once KOZ-9's roles/permissions model
 * reaches the frontend.
 */
const tenantNavMain: NavMainItem[] = [
  {
    title: "Dashboard",
    url: "/dashboard",
    icon: LayoutDashboard,
  },
  {
    title: "Instellingen",
    url: "#",
    icon: Settings,
  },
]

/**
 * The admin menu: shown on the reserved `admin` subdomain, see
 * lib/context/app-context.ts. Placeholder targets ("#") for items whose
 * real pages don't exist yet — this ticket only ships the shell/menu
 * structure, not page content (KOZ-14 out of scope).
 *
 * KOZ-14 rework: the admin dashboard now lives at the same `/dashboard`
 * path as the tenant dashboard — there is no separate `/admin` route
 * anymore, the Host subdomain alone decides which menu/content renders.
 */
const adminNavMain: NavMainItem[] = [
  {
    title: "Admin dashboard",
    url: "/dashboard",
    icon: LayoutDashboard,
  },
  {
    title: "Tenants",
    url: "/tenants",
    icon: Building2,
  },
  {
    title: "Gebruikers",
    url: "#",
    icon: Users,
  },
  {
    title: "Instellingen",
    url: "#",
    icon: Settings,
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

/**
 * Single source of truth for "which menu belongs to which context" — the
 * shared layout (components/app-shell.tsx) is the only caller, so a menu
 * never diverges between two places rendering "the same" sidebar.
 */
export function getNavMainForContext(context: AppContext): NavMainItem[] {
  return context === "admin" ? adminNavMain : tenantNavMain
}
