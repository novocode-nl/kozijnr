"use client"

import * as React from "react"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { TenantUserFormDialog } from "@/components/tenants/tenant-user-form-dialog"
import { TenantUserCredentialsDialog } from "@/components/tenants/tenant-user-credentials-dialog"
import { listTenantUsers, type CreatedTenantUser, type TenantUserSummary } from "@/lib/api"
import { roleLabel } from "@/lib/i18n/role-labels"

type LoadState =
  | { status: "loading" }
  | { status: "error" }
  | { status: "loaded"; users: TenantUserSummary[] }

/**
 * "Gebruikers" tab on the tenant detail page (KOZ-27, extended KOZ-31):
 * lists the tenant users that live inside this tenant's own Postgres
 * schema, plus a "Gebruiker toevoegen" action to create an additional one
 * with a chosen role. Editing/removing/deactivating an *existing* tenant
 * user stays out of scope (unchanged from KOZ-27).
 *
 * Relies on the caller keying this component by `subdomain` (the detail
 * page does, via its outer `key={subdomain}` wrapper) so switching tenants
 * remounts it with a fresh "loading" state, rather than this component
 * resetting its own state mid-life inside the effect below.
 */
export function TenantUsersTab({ subdomain }: { subdomain: string }) {
  const { t } = useTranslation()
  const [state, setState] = React.useState<LoadState>({ status: "loading" })
  const [addOpen, setAddOpen] = React.useState(false)
  const [credentials, setCredentials] = React.useState<CreatedTenantUser | null>(null)
  const [credentialsOpen, setCredentialsOpen] = React.useState(false)

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

  function handleCreated(user: CreatedTenantUser) {
    // Prepend the freshly created user so the tab reflects it immediately
    // (KOZ-31 DoD), without a full refetch.
    setState((current) =>
      current.status === "loaded"
        ? { status: "loaded", users: [{ email: user.email, roles: user.roles }, ...current.users] }
        : { status: "loaded", users: [{ email: user.email, roles: user.roles }] }
    )
    setCredentials(user)
    setCredentialsOpen(true)
  }

  const addAction = (
    <Button onClick={() => setAddOpen(true)}>{t("users.addUser")}</Button>
  )

  const dialogs = (
    <>
      <TenantUserFormDialog
        open={addOpen}
        onOpenChange={setAddOpen}
        subdomain={subdomain}
        onCreated={handleCreated}
      />
      <TenantUserCredentialsDialog
        open={credentialsOpen}
        onOpenChange={setCredentialsOpen}
        credentials={credentials}
      />
    </>
  )

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
    return (
      <div className="flex flex-col gap-4">
        <div className="flex justify-end">{addAction}</div>
        <p className="text-sm text-muted-foreground">{t("users.empty")}</p>
        {dialogs}
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex justify-end">{addAction}</div>
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
      {dialogs}
    </div>
  )
}
