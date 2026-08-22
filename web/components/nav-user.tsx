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
  DropdownMenuGroup,
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
import { adminLogout, logout } from "@/lib/api"
import type { AppContext } from "@/lib/context/app-context"
import { contextLabel } from "@/lib/navigation/menu-config"

/**
 * No "current user" profile endpoint exists yet, so this shows the context
 * label rather than a real name/avatar/email.
 */
export function NavUser({ context }: { context: AppContext }) {
  const { isMobile } = useSidebar()
  const router = useRouter()

  async function handleLogout() {
    // Invalidate the session on the API first, so a subsequent visit to /
    // bounces back to /login instead of the "already valid session -> /"
    // redirect in proxy.ts firing again.
    await (context === "admin" ? adminLogout() : logout())
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
            {/* DropdownMenuLabel needs a Menu.Group ancestor or it throws. */}
            <DropdownMenuGroup>
              <DropdownMenuLabel className="text-xs text-muted-foreground">
                {contextLabel[context]}
              </DropdownMenuLabel>
              <DropdownMenuSeparator />
              {/* base-ui's Menu.Item uses `onClick`, not Radix's `onSelect`. */}
              <DropdownMenuItem onClick={handleLogout}>
                <LogOut />
                Uitloggen
              </DropdownMenuItem>
            </DropdownMenuGroup>
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarMenuItem>
    </SidebarMenu>
  )
}
