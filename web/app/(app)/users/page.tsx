"use client"

import * as React from "react"
import { useTranslation } from "react-i18next"

import { PageHeading } from "@/components/page-heading"
import { AdminUsersTable } from "@/components/admin-users/admin-users-table"
import { AdminUserFormDialog } from "@/components/admin-users/admin-user-form-dialog"
import { AdminUserCredentialsDialog } from "@/components/admin-users/admin-user-credentials-dialog"
import { Button } from "@/components/ui/button"
import { contextLabel } from "@/lib/navigation/menu-config"
import type { AdminUserCredentials, CreatedAdminUser } from "@/lib/api"

/**
 * Admin user overview page (KOZ-30): renders inside the shared admin/tenant
 * shell (app/(app)/layout.tsx) at /users — reachable via the "Gebruikers"
 * item in the admin sidebar (lib/navigation/menu-config.ts). Lets a super
 * admin create a new admin-user account (email only, ROLE_SUPER_ADMIN,
 * generated password) without terminal access to `super-admin:create`.
 *
 * Modeled directly on the tenant overview page (KOZ-27): a "Nieuwe
 * gebruiker" action opens `<AdminUserFormDialog>`, and a successful create
 * shows the generated password once via `<AdminUserCredentialsDialog>`. No
 * active/archived tabs here — deactivating/removing admin users is out of
 * scope for KOZ-30, so there's only the one list.
 */
export default function UsersPage() {
  const { t } = useTranslation()
  const [createOpen, setCreateOpen] = React.useState(false)
  const [refreshToken, setRefreshToken] = React.useState(0)
  const [createdUser, setCreatedUser] = React.useState<AdminUserCredentials | null>(null)

  function handleCreated(user: CreatedAdminUser) {
    setRefreshToken((token) => token + 1)
    setCreatedUser({ email: user.email, password: user.password })
  }

  return (
    <div className="flex flex-1 flex-col gap-6">
      <PageHeading
        title={t("users.pageTitle")}
        breadcrumbs={[{ label: contextLabel.admin, href: "/" }, { label: t("users.pageTitle") }]}
        actions={<Button onClick={() => setCreateOpen(true)}>{t("users.newUser")}</Button>}
      />
      {/*
        Keyed on refreshToken: remounting is how this page forces
        AdminUsersTable to refetch from scratch right after creating a user
        — see TenantsPage's identical pattern.
      */}
      <AdminUsersTable key={refreshToken} />
      <AdminUserFormDialog open={createOpen} onOpenChange={setCreateOpen} onCreated={handleCreated} />
      <AdminUserCredentialsDialog
        open={createdUser !== null}
        onOpenChange={(open) => {
          if (!open) setCreatedUser(null)
        }}
        credentials={createdUser}
      />
    </div>
  )
}
