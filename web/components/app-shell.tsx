import type { ReactNode } from "react"

import { AppSidebar } from "@/components/app-sidebar"
import { Separator } from "@/components/ui/separator"
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "@/components/ui/sidebar"
import type { AppContext } from "@/lib/context/app-context"
import { contextLabel } from "@/lib/navigation/menu-config"

/**
 * The shared page shell for both the admin and tenant environments
 * (KOZ-14 DoD: "Admin-omgeving en tenant-omgeving gebruiken dezelfde
 * layoutcomponent met een eigen menu-configuratie"). One component, one
 * `context` prop — every future admin/tenant page renders its content as
 * `children` here rather than reimplementing the sidebar shell itself.
 *
 * Content of individual dashboard pages is explicitly out of scope for
 * this ticket — this only provides the shell they'll render inside.
 */
export function AppShell({
  context,
  children,
}: {
  context: AppContext
  children: ReactNode
}) {
  return (
    <SidebarProvider>
      <AppSidebar context={context} />
      <SidebarInset>
        <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
          <div className="flex items-center gap-2 px-4">
            <SidebarTrigger className="-ml-1" />
            <Separator
              orientation="vertical"
              className="mr-2 data-[orientation=vertical]:h-4"
            />
            <span className="text-sm font-medium text-muted-foreground">
              {contextLabel[context]}
            </span>
          </div>
        </header>
        <div className="flex flex-1 flex-col gap-4 p-4 pt-0">{children}</div>
      </SidebarInset>
    </SidebarProvider>
  )
}
