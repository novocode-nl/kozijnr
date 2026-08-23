import { Fragment, type ReactNode } from "react"
import Link from "next/link"

import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb"

/** One crumb in a `<PageHeading>` trail. The last crumb should omit `href`
 *  — it renders as the current, non-interactive page. */
export interface PageBreadcrumbItem {
  label: string
  href?: string
}

/**
 * Generic page-heading pattern: an optional breadcrumb trail, title on the
 * left, an optional actions slot (built by the caller from shadcn's
 * `Button` etc.) on the right. Deliberately not tied to any single
 * page/entity — every overview and detail screen reuses this instead of
 * hand-rolling its own header markup (KOZ-27 adds the breadcrumb trail,
 * following the shadcn dashboard-block convention of clickable breadcrumbs
 * above the page title).
 */
interface PageHeadingProps {
  title: string
  description?: string
  actions?: ReactNode
  breadcrumbs?: PageBreadcrumbItem[]
}

export function PageHeading({ title, description, actions, breadcrumbs }: PageHeadingProps) {
  return (
    <div className="flex flex-col gap-4">
      {breadcrumbs && breadcrumbs.length > 0 ? (
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
      ) : null}
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
    </div>
  )
}
