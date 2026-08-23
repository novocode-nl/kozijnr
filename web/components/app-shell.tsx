import type { ReactNode } from "react"

import { AppSidebar } from "@/components/app-sidebar"
import { HeaderBreadcrumb } from "@/components/header-breadcrumb"
import { Separator } from "@/components/ui/separator"
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "@/components/ui/sidebar"
import type { AppContext } from "@/lib/context/app-context"
import { BreadcrumbProvider } from "@/lib/context/breadcrumb-context"
import { contextLabel } from "@/lib/navigation/menu-config"

/**
 * Shared page shell for both the admin and tenant environments.
 *
 * KOZ-27 rework (2nd functional review): the header follows shadcn's
 * sidebar-07 block exactly — `SidebarTrigger`, a vertical `Separator`,
 * then the breadcrumb trail the active page registered (via
 * `<PageHeading>`'s `usePageBreadcrumbs`), instead of a breadcrumb
 * rendered loose above the page title. `<HeaderBreadcrumb>` falls back to
 * the static admin/tenant context label on pages that don't register a
 * trail.
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
      <BreadcrumbProvider>
        <AppSidebar context={context} />
        <SidebarInset>
          <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
            <div className="flex items-center gap-2 px-4">
              <SidebarTrigger className="-ml-1" />
              <Separator
                orientation="vertical"
                className="mr-2 self-center data-[orientation=vertical]:h-4 data-[orientation=vertical]:self-center"
              />
              <HeaderBreadcrumb fallback={contextLabel[context]} />
            </div>
          </header>
          <div className="flex flex-1 flex-col gap-4 p-4 pt-0">{children}</div>
        </SidebarInset>
      </BreadcrumbProvider>
    </SidebarProvider>
  )
}
