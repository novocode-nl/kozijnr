"use client"

import * as React from "react"
import { useTranslation } from "react-i18next"

import { PageHeading } from "@/components/page-heading"
import { TenantUserFormDialog } from "@/components/tenants/tenant-user-form-dialog"
import { TenantUserCredentialsDialog } from "@/components/tenants/tenant-user-credentials-dialog"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { contextLabel } from "@/lib/navigation/menu-config"
import { roleLabel } from "@/lib/i18n/role-labels"
import { TenantUser } from "@/lib/domain/tenant-user-roles"
import { createOwnTenantUser, getMe, listOwnTenantUsers, type CreatedTenantUser, type TenantUserSummary } from "@/lib/api"

type LoadState =
  | { status: "loading" }
  | { status: "error" }
  | { status: "loaded"; users: TenantUserSummary[]; isTenantAdmin: boolean }

/**
 * Tenant-own "Gebruikers" page (KOZ-31 rework): lets a logged-in
 * tenant-admin create additional users for their *own* tenant directly
 * from {tenant}.<domein>/users — the self-service counterpart of the
 * "Gebruikers" tab on the admin tenant detail page
 * (components/tenants/tenant-users-tab.tsx), reusing the exact same
 * dialog/credentials-dialog components with a different submit `action`
 * (createOwnTenantUser instead of createTenantUser(subdomain, ...)).
 *
 * The "Gebruiker toevoegen" action is only shown to a ROLE_TENANT_ADMIN —
 * checked here via GET /api/me purely for UI purposes; the backend
 * (CreateOwnTenantUserController) enforces the same role gate
 * independently, so a non-admin can never actually create a user even by
 * calling the endpoint directly.
 *
 * Rendered only for the tenant context: `/users` is a single shared route
 * for both admin.<domein> and {tenant}.<domein> — see `page.tsx` in this
 * directory, which picks between this component and `AdminUsersPage` via
 * `getAppContext()`.
 */
export default function OwnUsersPage() {
  const { t } = useTranslation()
  const [state, setState] = React.useState<LoadState>({ status: "loading" })
  const [addOpen, setAddOpen] = React.useState(false)
  const [credentials, setCredentials] = React.useState<CreatedTenantUser | null>(null)
  const [credentialsOpen, setCredentialsOpen] = React.useState(false)

  React.useEffect(() => {
    let cancelled = false

    Promise.all([listOwnTenantUsers(), getMe()])
      .then(([users, me]) => {
        if (!cancelled) {
          setState({
            status: "loaded",
            users,
            isTenantAdmin: me?.roles.includes(TenantUser.ROLE_TENANT_ADMIN) ?? false,
          })
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
  }, [])

  function handleCreated(user: CreatedTenantUser) {
    setState((current) =>
      current.status === "loaded"
        ? { ...current, users: [{ email: user.email, roles: user.roles }, ...current.users] }
        : { status: "loaded", users: [{ email: user.email, roles: user.roles }], isTenantAdmin: true }
    )
    setCredentials(user)
    setCredentialsOpen(true)
  }

  const isTenantAdmin = state.status === "loaded" && state.isTenantAdmin

  const addAction = isTenantAdmin ? (
    <Button onClick={() => setAddOpen(true)}>{t("users.addUser")}</Button>
  ) : null

  const dialogs = (
    <>
      <TenantUserFormDialog
        open={addOpen}
        onOpenChange={setAddOpen}
        action={createOwnTenantUser}
        onCreated={handleCreated}
      />
      <TenantUserCredentialsDialog
        open={credentialsOpen}
        onOpenChange={setCredentialsOpen}
        credentials={credentials}
      />
    </>
  )

  return (
    <div className="flex flex-1 flex-col gap-6">
      <PageHeading
        title={t("users.pageTitle")}
        breadcrumbs={[{ label: contextLabel.tenant, href: "/" }, { label: t("users.pageTitle") }]}
        actions={addAction}
      />

      {state.status === "loading" && (
        <div className="flex flex-col gap-2">
          <Skeleton className="h-8 w-full" />
          <Skeleton className="h-8 w-full" />
          <Skeleton className="h-8 w-full" />
        </div>
      )}

      {state.status === "error" && (
        <p className="text-sm text-destructive">{t("users.loadError")}</p>
      )}

      {state.status === "loaded" && state.users.length === 0 && (
        <p className="text-sm text-muted-foreground">{t("users.empty")}</p>
      )}

      {state.status === "loaded" && state.users.length > 0 && (
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
      )}

      {dialogs}
    </div>
  )
}
