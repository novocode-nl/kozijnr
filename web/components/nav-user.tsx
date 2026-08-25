"use client"

import { useRouter } from "next/navigation"
import { ChevronsUpDown, Languages, LogOut, UserRound } from "lucide-react"
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
 *
 * KOZ-33: also hosts the "Mijn profiel" link, tenant-only for now — the
 * profile page (`/profile`) is a tenant-only route (no admin equivalent
 * exists yet, see app/(app)/profile/page.tsx), so the item is hidden
 * entirely in the admin context rather than linking to a page that 404s.
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

    // KOZ-34 rework: a full navigation (`window.location`), not
    // `router.push`. The root layout (app/layout.tsx) reads the locale
    // cookie and is shared by every route including /login, so a
    // client-side/soft navigation reuses its already-rendered output
    // instead of re-running it — the freshly set `kozijnr_locale` cookie
    // (proxy.ts's withTenantDefaultLocaleCookie, which runs again on this
    // navigation regardless) would sit in the browser's cookie jar but
    // never reach the already-mounted I18nProvider, whose i18next instance
    // is seeded once from `initialLocale` on mount (see
    // components/providers/i18n-provider.tsx) and is never recreated by a
    // soft nav. A full navigation forces the root layout — and therefore
    // I18nProvider — to remount and read the current cookie, so a changed
    // tenant default locale shows up on /login immediately instead of only
    // after a hard refresh.
    //
    // No automated test covers this fix directly: vitest.config.mts only
    // runs `environment: "node"` against `*.test.ts` files (no jsdom/RTL
    // setup exists in this project yet), so there's no harness to mount
    // this client component, call handleLogout, and assert on
    // `window.location` vs. the Next.js router. The mechanism itself (root
    // layout not re-running on a soft nav) is also Next's App Router
    // internals, not something proxy.ts's already-server-only tenant-locale
    // lookup can be unit-tested against either. This was instead verified
    // manually: change the tenant's default locale in Instellingen, log
    // out, and confirm /login shows the new language without a hard
    // refresh — same "manually verified against the running app" discipline
    // as KOZ-32's upload-limit fix where PHPUnit couldn't reach the php.ini
    // layer either.
    // eslint-disable-next-line @next/next/no-location-assign-relative-destination -- intentional full navigation, see comment above.
    window.location.href = "/login"
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
              {context === "tenant" && (
                <>
                  <DropdownMenuItem onClick={() => router.push("/profile")}>
                    <UserRound />
                    {t("nav.profile")}
                  </DropdownMenuItem>
                  <DropdownMenuSeparator />
                </>
              )}
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
