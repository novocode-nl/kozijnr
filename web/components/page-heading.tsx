"use client"

import type { ReactNode } from "react"

import { usePageBreadcrumbs, type PageBreadcrumbItem } from "@/lib/context/breadcrumb-context"

export type { PageBreadcrumbItem }

/**
 * Generic page-heading pattern: title on the left, an optional actions
 * slot (built by the caller from shadcn's `Button` etc.) on the right.
 * Deliberately not tied to any single page/entity — every overview and
 * detail screen reuses this instead of hand-rolling its own header markup.
 *
 * KOZ-27 rework (2nd functional review): `breadcrumbs` no longer render
 * inline here — they're registered with the shared app-shell header via
 * `usePageBreadcrumbs`, which renders them next to the sidebar-collapse
 * trigger following the shadcn sidebar-07 pattern (see
 * `components/header-breadcrumb.tsx` and `components/app-shell.tsx`).
 * Callers keep passing `breadcrumbs` unchanged — only where they end up
 * rendering changed.
 */
interface PageHeadingProps {
  title: string
  description?: string
  actions?: ReactNode
  breadcrumbs?: PageBreadcrumbItem[]
}

export function PageHeading({ title, description, actions, breadcrumbs }: PageHeadingProps) {
  usePageBreadcrumbs(breadcrumbs ?? [])

  return (
    <div className="flex flex-wrap items-center justify-between gap-4">
      <div className="space-y-1">
        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
        {description ? (
          <p className="text-sm text-muted-foreground">{description}</p>
        ) : null}
      </div>
      {actions ? (
        <div className="flex items-center gap-2">{actions}</div>
      ) : null}
    </div>
  )
}
