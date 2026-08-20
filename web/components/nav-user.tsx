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
    // Both contexts now have a real frontend session to invalidate
    // (KOZ-14 rework, round 5 added the admin login form + PHPSESSID
    // session; round 6 fixed this call to actually invalidate it instead
    // of just navigating away): tenant calls the KOZ-13 logout proxy
    // (app/api/logout/route.ts), admin calls its counterpart
    // (app/api/admin/logout/route.ts) — both invalidate the session
    // server-side before the redirect below, so a subsequent /dashboard
    // visit correctly bounces back to /login instead of the round-5
    // "already valid session -> /dashboard" redirect firing again.
    await fetch(context === "admin" ? "/api/admin/logout" : "/api/logout", {
      method: "POST",
    })
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
            {/*
              This project's dropdown-menu primitive (components/ui/dropdown-menu.tsx,
              shadcn's base-ui-backed "base-nova" style) implements
              DropdownMenuLabel via base-ui's Menu.GroupLabel, which throws
              ("MenuGroupContext is missing") unless it has a Menu.Group
              ancestor — unlike the Radix-based shadcn reference, a bare
              DropdownMenuLabel is not enough here. Wrapping everything in a
              single DropdownMenuGroup satisfies that requirement (KOZ-14
              rework, round 6 — this crashed the whole menu, including the
              logout item below, on every open before this fix).
            */}
            <DropdownMenuGroup>
              <DropdownMenuLabel className="text-xs text-muted-foreground">
                {contextLabel[context]}
              </DropdownMenuLabel>
              <DropdownMenuSeparator />
              {/*
                base-ui's Menu.Item (components/ui/dropdown-menu.tsx's
                DropdownMenuItem) exposes `onClick`, not Radix's `onSelect`
                — an `onSelect` prop is silently dropped as an unrecognized
                DOM attribute and never fires (KOZ-14 rework, round 6: this
                is the actual reason clicking "Uitloggen" did nothing).
              */}
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
