"use client"

import { useRouter } from "next/navigation"
import { ChevronsUpDown, LogOut } from "lucide-react"

import {
  Avatar,
  AvatarFallback,
} from "@/components/ui/avatar"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from "@/components/ui/sidebar"
import type { AppContext } from "@/lib/context/app-context"
import { contextLabel } from "@/lib/navigation/menu-config"

/**
 * Adapted from the shadcn `sidebar-07` block's `NavUser`. Simplified: this
 * ticket (KOZ-14) is layout/shell only, and there is no "current user"
 * profile endpoint wired up yet for either context (tenant or admin), so
 * this shows the context label rather than a real name/avatar/email —
 * swap in real profile data once a `/api/me`-style endpoint exists.
 */
export function NavUser({ context }: { context: AppContext }) {
  const { isMobile } = useSidebar()
  const router = useRouter()

  async function handleLogout() {
    // Only the tenant-user login (KOZ-13) has a real logout endpoint to
    // call — the admin context has no frontend session at all yet (see
    // lib/context/app-context.ts), so there is nothing to call there.
    if (context === "tenant") {
      await fetch("/api/logout", { method: "POST" })
    }
    router.push("/login")
  }

  return (
    <SidebarMenu>
      <SidebarMenuItem>
        <DropdownMenu>
          <DropdownMenuTrigger
            render={
              <SidebarMenuButton
                size="lg"
                className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
              >
                <Avatar className="h-8 w-8 rounded-lg">
                  <AvatarFallback className="rounded-lg">
                    {context === "admin" ? "AD" : "TN"}
                  </AvatarFallback>
                </Avatar>
                <div className="grid flex-1 text-left text-sm leading-tight">
                  <span className="truncate font-medium">
                    {contextLabel[context]}
                  </span>
                </div>
                <ChevronsUpDown className="ml-auto size-4" />
              </SidebarMenuButton>
            }
          />
          <DropdownMenuContent
            className="min-w-56 rounded-lg"
            side={isMobile ? "bottom" : "right"}
            align="end"
            sideOffset={4}
          >
            <DropdownMenuLabel className="text-xs text-muted-foreground">
              {contextLabel[context]}
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem onSelect={handleLogout}>
              <LogOut />
              Uitloggen
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarMenuItem>
    </SidebarMenu>
  )
}
