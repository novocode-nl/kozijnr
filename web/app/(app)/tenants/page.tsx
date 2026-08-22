import Link from "next/link"

import { PageHeading } from "@/components/page-heading"
import { TenantsTable } from "@/components/tenants/tenants-table"
import { Button } from "@/components/ui/button"

/**
 * Tenant overview page: renders inside the shared admin/tenant shell
 * (app/(app)/layout.tsx) at /tenants — reachable via the "Tenants" item in
 * the admin sidebar (lib/navigation/menu-config.ts). The "Nieuwe tenant"
 * action navigates to the create screen (KOZ-25); per-row edit actions
 * live in `TenantsTable` itself.
 */
export default function TenantsPage() {
  return (
    <div className="flex flex-1 flex-col gap-6">
      <PageHeading
        title="Tenants"
        actions={
          <Button render={<Link href="/tenants/new" />}>Nieuwe tenant</Button>
        }
      />
      <TenantsTable />
    </div>
  )
}
