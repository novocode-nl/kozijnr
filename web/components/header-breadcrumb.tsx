"use client"

import { Fragment } from "react"
import Link from "next/link"

import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb"
import { useBreadcrumbs } from "@/lib/context/breadcrumb-context"

/**
 * Renders the breadcrumb trail the active page registered via
 * `usePageBreadcrumbs` (see `<PageHeading>`), following the shadcn
 * sidebar-07 header pattern: next to the sidebar-collapse trigger, not
 * above the page title. Falls back to `fallback` (a static context label)
 * when the active page hasn't registered a trail.
 */
export function HeaderBreadcrumb({ fallback }: { fallback?: string }) {
  const breadcrumbs = useBreadcrumbs()

  if (breadcrumbs.length === 0) {
    return fallback ? (
      <span className="text-sm font-medium text-muted-foreground">{fallback}</span>
    ) : null
  }

  return (
    <Breadcrumb>
      <BreadcrumbList>
        {breadcrumbs.map((crumb, index) => (
          <Fragment key={`${crumb.label}-${index}`}>
            {index > 0 ? <BreadcrumbSeparator /> : null}
            <BreadcrumbItem>
              {crumb.href ? (
                <BreadcrumbLink render={<Link href={crumb.href} />}>
                  {crumb.label}
                </BreadcrumbLink>
              ) : (
                <BreadcrumbPage>{crumb.label}</BreadcrumbPage>
              )}
            </BreadcrumbItem>
          </Fragment>
        ))}
      </BreadcrumbList>
    </Breadcrumb>
  )
}
