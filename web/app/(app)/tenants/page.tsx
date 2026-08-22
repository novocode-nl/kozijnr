import { PageHeading } from "@/components/page-heading"
import { TenantsTable } from "@/components/tenants/tenants-table"

/**
 * Tenant overview page: renders inside the shared admin/tenant shell
 * (app/(app)/layout.tsx) at /tenants — reachable via the "Tenants" item in
 * the admin sidebar (lib/navigation/menu-config.ts). No CRUD actions here
 * by design — the `PageHeading`'s actions slot is intentionally unused for
 * now.
 */
export default function TenantsPage() {
  return (
    <div className="flex flex-1 flex-col gap-6">
      <PageHeading title="Tenants" />
      <TenantsTable />
    </div>
  )
}
