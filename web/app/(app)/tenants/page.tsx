"use client"

import * as React from "react"

import { PageHeading } from "@/components/page-heading"
import { TenantsTable } from "@/components/tenants/tenants-table"
import { TenantFormDialog } from "@/components/tenants/tenant-form-dialog"
import { TenantAdminCredentialsDialog } from "@/components/tenants/tenant-admin-credentials-dialog"
import { Button } from "@/components/ui/button"
import { Switch } from "@/components/ui/switch"
import { Label } from "@/components/ui/label"
import type { CreatedTenant, TenantAdminCredentials } from "@/lib/api"

/**
 * Tenant overview page: renders inside the shared admin/tenant shell
 * (app/(app)/layout.tsx) at /tenants — reachable via the "Tenants" item in
 * the admin sidebar (lib/navigation/menu-config.ts).
 *
 * KOZ-27 replaces KOZ-25's page-based create/edit flow with dialogs: the
 * "Nieuwe tenant" action opens `<TenantFormDialog>` here, edit/archive
 * actions live in `<TenantsTable>`'s kebab menu. Also owns the
 * active/archived toggle: `<TenantsTable>` refetches from the backend
 * whenever it flips rather than filtering a single fetched list
 * client-side.
 */
export default function TenantsPage() {
  const [showArchived, setShowArchived] = React.useState(false)
  const [createOpen, setCreateOpen] = React.useState(false)
  const [refreshToken, setRefreshToken] = React.useState(0)
  const [createdAdmin, setCreatedAdmin] = React.useState<TenantAdminCredentials | null>(null)

  function handleCreated(tenant: CreatedTenant) {
    setRefreshToken((token) => token + 1)
    setCreatedAdmin(tenant.tenantAdmin)
    // A freshly created tenant is always active — if the archived view is
    // showing, switch back to active so the new tenant is actually visible.
    setShowArchived(false)
  }

  return (
    <div className="flex flex-1 flex-col gap-6">
      <PageHeading
        title="Tenants"
        actions={<Button onClick={() => setCreateOpen(true)}>Nieuwe tenant</Button>}
      />
      <div className="flex items-center gap-2">
        <Switch id="show-archived" checked={showArchived} onCheckedChange={setShowArchived} />
        <Label htmlFor="show-archived">Toon gearchiveerde tenants</Label>
      </div>
      {/*
        Keyed on showArchived + refreshToken: remounting is how this page
        forces TenantsTable to refetch from scratch (e.g. right after
        creating a tenant), since the table itself never resets its own
        state mid-life — see TenantsTable's comment.
      */}
      <TenantsTable key={`${showArchived}-${refreshToken}`} showArchived={showArchived} />
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
