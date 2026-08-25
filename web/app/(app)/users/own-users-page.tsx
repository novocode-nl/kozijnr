"use client"

import * as React from "react"
import { useTranslation } from "react-i18next"

import { PageHeading } from "@/components/page-heading"
import { TenantUserFormDialog } from "@/components/tenants/tenant-user-form-dialog"
import { CredentialsDialog } from "@/components/credentials-dialog"
import { UsersTable } from "@/components/users/users-table"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { contextLabel } from "@/lib/navigation/menu-config"
import { TenantUser } from "@/lib/domain/tenant-user-roles"
import { createOwnTenantUser, getMe, listOwnTenantUsers, type CreatedTenantUser, type TenantUserSummary } from "@/lib/api"
import { useLoadState } from "@/lib/hooks/use-load-state"

type OwnUsersData = { users: TenantUserSummary[]; isTenantAdmin: boolean }

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
  const [state, setState] = useLoadState<OwnUsersData>(
    () =>
      Promise.all([listOwnTenantUsers(), getMe()]).then(([users, me]) => ({
        users,
        isTenantAdmin: me?.roles.includes(TenantUser.ROLE_TENANT_ADMIN) ?? false,
      })),
    []
  )
  const [addOpen, setAddOpen] = React.useState(false)
  const [credentials, setCredentials] = React.useState<CreatedTenantUser | null>(null)
  const [credentialsOpen, setCredentialsOpen] = React.useState(false)

  function handleCreated(user: CreatedTenantUser) {
    setState((current) =>
      current.status === "loaded"
        ? { ...current, data: { ...current.data, users: [{ email: user.email, roles: user.roles }, ...current.data.users] } }
        : { status: "loaded", data: { users: [{ email: user.email, roles: user.roles }], isTenantAdmin: true } }
    )
    setCredentials(user)
    setCredentialsOpen(true)
  }

  const isTenantAdmin = state.status === "loaded" && state.data.isTenantAdmin

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
      <CredentialsDialog
        i18nPrefix="users.credentials"
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

      {state.status === "loaded" && state.data.users.length === 0 && (
        <p className="text-sm text-muted-foreground">{t("users.empty")}</p>
      )}

      {state.status === "loaded" && state.data.users.length > 0 && (
        <UsersTable users={state.data.users} />
      )}

      {dialogs}
    </div>
  )
}
