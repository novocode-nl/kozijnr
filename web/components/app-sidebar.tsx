"use client"

import * as React from "react"

import { NavMain } from "@/components/nav-main"
import { NavSecondary } from "@/components/nav-secondary"
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
import {
  contextIcon,
  contextLabel,
  getNavMainForContext,
  getNavSecondaryForContext,
} from "@/lib/navigation/menu-config"

/**
 * The one sidebar shell shared by the admin and tenant environments.
 * `"use client"`: menu items carry a `LucideIcon` component reference,
 * which isn't serializable across the Server/Client Component boundary —
 * resolving the menu here keeps the whole subtree on one side of it.
 */
export function AppSidebar({
  context,
  ...props
}: { context: AppContext } & React.ComponentProps<typeof Sidebar>) {
  const items = getNavMainForContext(context)
  const secondaryItems = getNavSecondaryForContext(context)
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
        <NavSecondary items={secondaryItems} className="mt-auto" />
      </SidebarContent>
      <SidebarFooter>
        <NavUser context={context} />
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}
