"use client"

import * as React from "react"

import {
  SidebarGroup,
  SidebarGroupContent,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar"
import type { NavSecondaryItem } from "@/lib/navigation/menu-config"

/**
 * Secondary nav group (from shadcn's dashboard-01 block), rendered below
 * the primary nav at the bottom of the sidebar. `icon` is a `LucideIcon`
 * component reference, mirroring `NavMain`'s convention, rather than the
 * pre-rendered `React.ReactNode` the stock dashboard-01 block uses.
 */
export function NavSecondary({
  items,
  ...props
}: {
  items: NavSecondaryItem[]
} & React.ComponentPropsWithoutRef<typeof SidebarGroup>) {
  return (
    <SidebarGroup {...props}>
      <SidebarGroupContent>
        <SidebarMenu>
          {items.map((item) => (
            <SidebarMenuItem key={item.title}>
              <SidebarMenuButton render={<a href={item.url} />}>
                <item.icon />
                <span>{item.title}</span>
              </SidebarMenuButton>
            </SidebarMenuItem>
          ))}
        </SidebarMenu>
      </SidebarGroupContent>
    </SidebarGroup>
  )
}
