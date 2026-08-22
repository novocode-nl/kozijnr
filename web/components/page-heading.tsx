import type { ReactNode } from "react"

/**
 * Generic page-heading pattern (KOZ-24): title on the left, an optional
 * actions slot (built by the caller from shadcn's `Button` etc., see
 * app/(app)/tenants/page.tsx for the shape once KOZ-25 adds an action) on
 * the right. Deliberately not tied to any single page/entity — every future
 * overview screen (KOZ-25 and beyond) reuses this instead of hand-rolling
 * its own header markup.
 */
interface PageHeadingProps {
  title: string
  description?: string
  actions?: ReactNode
}

export function PageHeading({ title, description, actions }: PageHeadingProps) {
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
