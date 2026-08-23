"use client"

import { useRouter } from "next/navigation"
import { ChevronsUpDown, Languages, LogOut } from "lucide-react"
import { useTranslation } from "react-i18next"

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
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
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
import { setClientLocale, type Locale } from "@/lib/i18n/locale"

/**
 * No "current user" profile endpoint exists yet, so this shows the context
 * label rather than a real name/avatar/email.
 *
 * KOZ-29: also hosts the language switch, under the user menu rather than
 * as a separate header widget — a "Taal" submenu with the two supported
 * locales as radio options. Selecting one calls `i18n.changeLanguage`
 * (re-renders every `useTranslation()` consumer in this component tree
 * immediately) and persists the choice in the locale cookie so it survives
 * a reload/new session — see lib/i18n/locale.ts.
 */
export function NavUser({ context }: { context: AppContext }) {
  const { isMobile } = useSidebar()
  const router = useRouter()
  const { t, i18n } = useTranslation()

  async function handleLogout() {
    // Invalidate the session on the API first, so a subsequent visit to /
    // bounces back to /login instead of the "already valid session -> /"
    // redirect in proxy.ts firing again.
    await (context === "admin" ? adminLogout() : logout())
    router.push("/login")
  }

  function handleLocaleChange(locale: string) {
    i18n.changeLanguage(locale)
    setClientLocale(locale as Locale)
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
              <DropdownMenuSub>
                <DropdownMenuSubTrigger>
                  <Languages />
                  {t("common.language")}
                </DropdownMenuSubTrigger>
                <DropdownMenuSubContent>
                  <DropdownMenuRadioGroup value={i18n.language} onValueChange={handleLocaleChange}>
                    <DropdownMenuRadioItem value="nl">{t("common.languageNl")}</DropdownMenuRadioItem>
                    <DropdownMenuRadioItem value="en">{t("common.languageEn")}</DropdownMenuRadioItem>
                  </DropdownMenuRadioGroup>
                </DropdownMenuSubContent>
              </DropdownMenuSub>
              <DropdownMenuSeparator />
              {/* base-ui's Menu.Item uses `onClick`, not Radix's `onSelect`. */}
              <DropdownMenuItem onClick={handleLogout}>
                <LogOut />
                {t("common.logout")}
              </DropdownMenuItem>
            </DropdownMenuGroup>
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarMenuItem>
    </SidebarMenu>
  )
}
