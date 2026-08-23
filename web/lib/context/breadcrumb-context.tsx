"use client"

import { createContext, useContext, useEffect, useState, type ReactNode } from "react"

/** One crumb in the header's breadcrumb trail. The last crumb should omit
 *  `href` — it renders as the current, non-interactive page. */
export interface PageBreadcrumbItem {
  label: string
  href?: string
}

interface BreadcrumbContextValue {
  breadcrumbs: PageBreadcrumbItem[]
  setBreadcrumbs: (items: PageBreadcrumbItem[]) => void
}

const BreadcrumbContext = createContext<BreadcrumbContextValue | null>(null)

/**
 * Provides the shared breadcrumb trail state for the whole app shell
 * (KOZ-27 rework): the trail is registered by the active page (via
 * `usePageBreadcrumbs`, called from `<PageHeading>`) and rendered by the
 * header (via `useBreadcrumbs`, in `<HeaderBreadcrumb>`), following the
 * shadcn sidebar-07 pattern where the breadcrumb lives next to the
 * sidebar-collapse trigger, not in the page body.
 */
export function BreadcrumbProvider({ children }: { children: ReactNode }) {
  const [breadcrumbs, setBreadcrumbs] = useState<PageBreadcrumbItem[]>([])

  return (
    <BreadcrumbContext.Provider value={{ breadcrumbs, setBreadcrumbs }}>
      {children}
    </BreadcrumbContext.Provider>
  )
}

function useBreadcrumbContext(): BreadcrumbContextValue {
  const context = useContext(BreadcrumbContext)
  if (!context) {
    throw new Error("useBreadcrumbContext must be used within a BreadcrumbProvider")
  }
  return context
}

/** Reads the breadcrumb trail currently registered by the active page — used by the header. */
export function useBreadcrumbs(): PageBreadcrumbItem[] {
  return useBreadcrumbContext().breadcrumbs
}

/**
 * Registers the active page's breadcrumb trail with the shared header.
 * Clears itself on unmount (and whenever the trail changes) so navigating
 * away never leaves a stale trail showing.
 */
export function usePageBreadcrumbs(breadcrumbs: PageBreadcrumbItem[]): void {
  const { setBreadcrumbs } = useBreadcrumbContext()
  const key = breadcrumbs.map((crumb) => `${crumb.label}:${crumb.href ?? ""}`).join("|")

  useEffect(() => {
    setBreadcrumbs(breadcrumbs)
    return () => setBreadcrumbs([])
    // Re-run only when the trail's actual content changes (`key`), not on
    // every render — `breadcrumbs` is a fresh array/object each render.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key])
}
