"use client"

import { useParams, useRouter } from "next/navigation"

import { PageHeading } from "@/components/page-heading"
import { ConfigForm } from "@/components/config-form/config-form"
import { Card, CardContent } from "@/components/ui/card"
import { updateTenant, type TenantNamePayload, type TenantSummary } from "@/lib/api"
import { tenantFormSchema, type TenantFormValues } from "@/lib/schemas/tenant"
import type { FieldConfig, FormAction } from "@/lib/forms/types"

/**
 * Tenant edit screen: a `<ConfigForm>` pre-filled (`defaultValues`) with
 * the tenant's current subdomain, taken straight from the route param —
 * `GET /api/admin/tenants` (TenantSummary) already carries nothing beyond
 * `subdomain`/`createdAt`, so no separate detail endpoint is needed to
 * fill this single-field form.
 *
 * Submits via `lib/api.ts`'s `updateTenant` (PATCH
 * /api/admin/tenants/{subdomain}), closing over the *current* subdomain
 * from the URL so a rename mid-edit still targets the right tenant. On
 * success, navigates back to the tenant overview (KOZ-24) — `TenantsTable`
 * refetches on mount, so the rename shows up there immediately.
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

export default function EditTenantPage() {
  const router = useRouter()
  const params = useParams<{ subdomain: string }>()
  const currentSubdomain = decodeURIComponent(params.subdomain)

  const action: FormAction<TenantNamePayload, TenantSummary> = (payload) =>
    updateTenant(currentSubdomain, payload)

  return (
    <div className="flex flex-1 flex-col gap-6">
      <PageHeading title="Tenant bewerken" description={`Wijzig de gegevens van "${currentSubdomain}".`} />
      <Card className="max-w-md">
        <CardContent>
          <ConfigForm
            fields={fields}
            schema={tenantFormSchema}
            defaultValues={{ subdomain: currentSubdomain }}
            action={action}
            transformSubmit={(values): TenantNamePayload => ({ name: values.subdomain })}
            onSuccess={() => router.push("/tenants")}
            submitLabel="Wijzigingen opslaan"
            pendingLabel="Bezig met opslaan..."
          />
        </CardContent>
      </Card>
    </div>
  )
}
