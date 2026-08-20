"use client"

import * as React from "react"

import { NavMain } from "@/components/nav-main"
import { NavUser } from "@/components/nav-user"
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarRail,
} from "@/components/ui/sidebar"
import type { AppContext } from "@/lib/context/app-context"
import { contextIcon, contextLabel, getNavMainForContext } from "@/lib/navigation/menu-config"

/**
 * The one sidebar shell shared by the admin and tenant environments
 * (KOZ-14). Adapted from the shadcn `sidebar-07` block: this project has
 * no "teams" or "projects" concept the block's `TeamSwitcher`/`NavProjects`
 * demo pieces map onto, so those were dropped rather than kept as
 * unrelated placeholder content — everything else (collapsible icon
 * sidebar, `NavMain`, `NavUser`, `SidebarRail`) carries over.
 *
 * `"use client"`: `getNavMainForContext` returns menu items carrying a
 * `LucideIcon` component reference (not a rendered element) — passing that
 * as a prop from a Server Component into the Client Components below
 * (`NavMain`, `NavUser`) isn't serializable ("Only plain objects can be
 * passed to Client Components from Server Components"). Resolving the menu
 * here, client-side, keeps the whole `AppSidebar` subtree on one side of
 * that boundary instead of threading icon components across it.
 *
 * Which menu renders is entirely a function of `context` — this component
 * itself has no opinion on admin vs. tenant, that decision is made once,
 * upstream, in lib/context/app-context.ts and passed down.
 */
export function AppSidebar({
  context,
  ...props
}: { context: AppContext } & React.ComponentProps<typeof Sidebar>) {
  const items = getNavMainForContext(context)
  const Icon = contextIcon[context]

  return (
    <Sidebar collapsible="icon" {...props}>
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg">
              <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                <Icon className="size-4" />
              </div>
              <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">
                  {contextLabel[context]}
                </span>
              </div>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>
      <SidebarContent>
        <NavMain items={items} />
      </SidebarContent>
      <SidebarFooter>
        <NavUser context={context} />
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}
