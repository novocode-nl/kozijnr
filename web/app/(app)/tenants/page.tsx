"use client"

import * as React from "react"
import { useTranslation } from "react-i18next"

import { PageHeading } from "@/components/page-heading"
import { TenantsTable } from "@/components/tenants/tenants-table"
import { TenantFormDialog } from "@/components/tenants/tenant-form-dialog"
import { TenantAdminCredentialsDialog } from "@/components/tenants/tenant-admin-credentials-dialog"
import { Button } from "@/components/ui/button"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { contextLabel } from "@/lib/navigation/menu-config"
import type { CreatedTenant, TenantAdminCredentials } from "@/lib/api"

type TenantView = "active" | "archived"

/**
 * Tenant overview page: renders inside the shared admin/tenant shell
 * (app/(app)/layout.tsx) at /tenants — reachable via the "Tenants" item in
 * the admin sidebar (lib/navigation/menu-config.ts).
 *
 * KOZ-27 replaces KOZ-25's page-based create/edit flow with dialogs: the
 * "Nieuwe tenant" action opens `<TenantFormDialog>` here, archive actions
 * live in `<TenantsTable>`'s kebab menu ("bewerken" moved to the detail
 * page — see that page's comment).
 *
 * The rework (functional review) replaces the earlier active/archived
 * on-off switch with a shadcn dashboard-block-style tab pair ("Actief" /
 * "Gearchiveerd") above the table. Each tab renders its own
 * `<TenantsTable>` instance so switching tabs is itself the "refetch from
 * the backend" trigger — `<TabsContent>` unmounts the inactive panel by
 * default, so there's no separate showArchived toggle to keep in sync.
 */
export default function TenantsPage() {
  const { t } = useTranslation()
  const [activeView, setActiveView] = React.useState<TenantView>("active")
  const [createOpen, setCreateOpen] = React.useState(false)
  const [refreshToken, setRefreshToken] = React.useState(0)
  const [createdAdmin, setCreatedAdmin] = React.useState<TenantAdminCredentials | null>(null)

  function handleCreated(tenant: CreatedTenant) {
    setRefreshToken((token) => token + 1)
    setCreatedAdmin(tenant.tenantAdmin)
    // A freshly created tenant is always active — if the archived tab is
    // showing, switch back to active so the new tenant is actually visible.
    setActiveView("active")
  }

  return (
    <div className="flex flex-1 flex-col gap-6">
      <PageHeading
        title={t("tenants.pageTitle")}
        breadcrumbs={[{ label: contextLabel.admin, href: "/" }, { label: t("tenants.pageTitle") }]}
        actions={<Button onClick={() => setCreateOpen(true)}>{t("tenants.newTenant")}</Button>}
      />
      <Tabs value={activeView} onValueChange={(value) => setActiveView(value as TenantView)}>
        <TabsList>
          <TabsTrigger value="active">{t("tenants.tabActive")}</TabsTrigger>
          <TabsTrigger value="archived">{t("tenants.tabArchived")}</TabsTrigger>
        </TabsList>
        {/*
          Keyed on refreshToken: remounting is how this page forces
          TenantsTable to refetch from scratch (e.g. right after creating a
          tenant), since the table itself never resets its own state
          mid-life — see TenantsTable's comment.
        */}
        <TabsContent value="active">
          <TenantsTable key={`active-${refreshToken}`} showArchived={false} />
        </TabsContent>
        <TabsContent value="archived">
          <TenantsTable key={`archived-${refreshToken}`} showArchived={true} />
        </TabsContent>
      </Tabs>
      <TenantFormDialog open={createOpen} onOpenChange={setCreateOpen} onCreated={handleCreated} />
      <TenantAdminCredentialsDialog
        open={createdAdmin !== null}
        onOpenChange={(open) => {
          if (!open) setCreatedAdmin(null)
        }}
        credentials={createdAdmin}
      />
    </div>
  )
}
