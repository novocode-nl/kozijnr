"use client"

import * as React from "react"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { TenantUserFormDialog } from "@/components/tenants/tenant-user-form-dialog"
import { CredentialsDialog } from "@/components/credentials-dialog"
import { UsersTable } from "@/components/users/users-table"
import { createTenantUser, listTenantUsers, type CreatedTenantUser } from "@/lib/api"
import { useLoadState } from "@/lib/hooks/use-load-state"

/**
 * "Gebruikers" tab on the tenant detail page (KOZ-27, extended KOZ-31):
 * lists the tenant users that live inside this tenant's own Postgres
 * schema, plus a "Gebruiker toevoegen" action to create an additional one
 * with a chosen role. Editing/removing/deactivating an *existing* tenant
 * user stays out of scope (unchanged from KOZ-27).
 *
 * Relies on the caller keying this component by `subdomain` (the detail
 * page does, via its outer `key={subdomain}` wrapper) so switching tenants
 * remounts it with a fresh "loading" state — deliberately kept alongside
 * the hook's own `[subdomain]` deps as belt & braces.
 */
export function TenantUsersTab({ subdomain }: { subdomain: string }) {
  const { t } = useTranslation()
  const [state, setState] = useLoadState(() => listTenantUsers(subdomain), [subdomain])
  const [addOpen, setAddOpen] = React.useState(false)
  const [credentials, setCredentials] = React.useState<CreatedTenantUser | null>(null)
  const [credentialsOpen, setCredentialsOpen] = React.useState(false)

  function handleCreated(user: CreatedTenantUser) {
    // Prepend the freshly created user so the tab reflects it immediately
    // (KOZ-31 DoD), without a full refetch.
    setState((current) =>
      current.status === "loaded"
        ? { status: "loaded", data: [{ email: user.email, roles: user.roles }, ...current.data] }
        : { status: "loaded", data: [{ email: user.email, roles: user.roles }] }
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
        action={(payload) => createTenantUser(subdomain, payload)}
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

  if (state.data.length === 0) {
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
      <UsersTable users={state.data} />
      {dialogs}
    </div>
  )
}
