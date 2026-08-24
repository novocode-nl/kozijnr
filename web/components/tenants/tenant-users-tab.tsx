"use client"

import * as React from "react"
import { useTranslation } from "react-i18next"

import { Skeleton } from "@/components/ui/skeleton"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { listTenantUsers, type TenantUserSummary } from "@/lib/api"
import { roleLabel } from "@/lib/i18n/role-labels"

type LoadState =
  | { status: "loading" }
  | { status: "error" }
  | { status: "loaded"; users: TenantUserSummary[] }

/**
 * "Gebruikers" tab on the tenant detail page (KOZ-27): lists the tenant
 * users that live inside this tenant's own Postgres schema. Read-only —
 * managing individual tenant users is explicitly out of scope for this
 * ticket.
 *
 * Relies on the caller keying this component by `subdomain` (the detail
 * page does, via its outer `key={subdomain}` wrapper) so switching tenants
 * remounts it with a fresh "loading" state, rather than this component
 * resetting its own state mid-life inside the effect below.
 */
export function TenantUsersTab({ subdomain }: { subdomain: string }) {
  const { t } = useTranslation()
  const [state, setState] = React.useState<LoadState>({ status: "loading" })

  React.useEffect(() => {
    let cancelled = false

    listTenantUsers(subdomain)
      .then((users) => {
        if (!cancelled) {
          setState({ status: "loaded", users })
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({ status: "error" })
        }
      })

    return () => {
      cancelled = true
    }
  }, [subdomain])

  if (state.status === "loading") {
    return (
      <div className="flex flex-col gap-2">
        <Skeleton className="h-8 w-full" />
        <Skeleton className="h-8 w-full" />
        <Skeleton className="h-8 w-full" />
      </div>
    )
  }

  if (state.status === "error") {
    return (
      <p className="text-sm text-destructive">
        {t("users.loadError")}
      </p>
    )
  }

  if (state.users.length === 0) {
    return <p className="text-sm text-muted-foreground">{t("users.empty")}</p>
  }

  return (
    <div className="overflow-hidden rounded-md border">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t("users.columnEmail")}</TableHead>
            <TableHead>{t("users.columnRoles")}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {state.users.map((user) => (
            <TableRow key={user.email}>
              <TableCell className="font-medium">{user.email}</TableCell>
              <TableCell className="text-muted-foreground">
                {user.roles.map((role) => roleLabel(role, t)).join(", ")}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  )
}
