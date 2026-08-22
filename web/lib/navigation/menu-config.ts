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

/** The tenant menu: identical for every tenant, no per-tenant items. */
const tenantNavMain: NavMainItem[] = [
  {
    title: "Dashboard",
    url: "/",
    icon: LayoutDashboard,
  },
  {
    title: "Instellingen",
    url: "#",
    icon: Settings,
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
    title: "Admin dashboard",
    url: "/",
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

/** Single source of truth for "which menu belongs to which context". */
export function getNavMainForContext(context: AppContext): NavMainItem[] {
  return context === "admin" ? adminNavMain : tenantNavMain
}
