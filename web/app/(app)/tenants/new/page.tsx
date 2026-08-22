"use client"

import { useRouter } from "next/navigation"

import { PageHeading } from "@/components/page-heading"
import { ConfigForm } from "@/components/config-form/config-form"
import { Card, CardContent } from "@/components/ui/card"
import { createTenant, type TenantNamePayload } from "@/lib/api"
import { tenantFormSchema, type TenantFormValues } from "@/lib/schemas/tenant"
import type { FieldConfig } from "@/lib/forms/types"

/**
 * Tenant creation screen: a `<ConfigForm>` with a single `subdomain` field,
 * `transformSubmit`-shaped into the `{ name }` payload
 * `lib/api.ts`'s `createTenant` (POST /api/admin/tenants) expects. On
 * success, navigates back to the tenant overview (KOZ-24) — `TenantsTable`
 * refetches on mount, so the new tenant shows up there immediately.
 */
const fields: FieldConfig<TenantFormValues>[] = [
  {
    name: "subdomain",
    label: "Subdomain",
    type: "text",
    placeholder: "acme",
    hint: "Kleine letters, cijfers en koppeltekens, bijv. \"acme\" of \"acme-bv\".",
    autoComplete: "off",
  },
]

const defaultValues: TenantFormValues = { subdomain: "" }

export default function NewTenantPage() {
  const router = useRouter()

  return (
    <div className="flex flex-1 flex-col gap-6">
      <PageHeading title="Nieuwe tenant" description="Maak een nieuwe tenant aan." />
      <Card className="max-w-md">
        <CardContent>
          <ConfigForm
            fields={fields}
            schema={tenantFormSchema}
            defaultValues={defaultValues}
            action={createTenant}
            transformSubmit={(values): TenantNamePayload => ({ name: values.subdomain })}
            onSuccess={() => router.push("/tenants")}
            submitLabel="Tenant aanmaken"
            pendingLabel="Bezig met aanmaken..."
          />
        </CardContent>
      </Card>
    </div>
  )
}
