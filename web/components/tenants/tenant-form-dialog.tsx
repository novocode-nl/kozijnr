"use client"

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { ConfigForm } from "@/components/config-form/config-form"
import { createTenant, updateTenant, type CreatedTenant, type TenantSummary } from "@/lib/api"
import { tenantFormSchema, type TenantFormValues } from "@/lib/schemas/tenant"
import type { FieldConfig } from "@/lib/forms/types"

/**
 * Shared field config for both the create and edit tenant dialogs (KOZ-27,
 * replacing KOZ-25's page-based create/edit flow). Form values already
 * match `TenantPayload` 1:1, so neither ConfigForm instantiation below
 * needs a `transformSubmit`.
 */
const fields: FieldConfig<TenantFormValues>[] = [
  {
    name: "name",
    label: "Naam",
    type: "text",
    placeholder: "Acme B.V.",
    autoComplete: "off",
  },
  {
    name: "slug",
    label: "Subdomain",
    type: "text",
    placeholder: "acme",
    hint: "Kleine letters, cijfers en koppeltekens, bijv. \"acme\" of \"acme-bv\".",
    autoComplete: "off",
  },
]

interface TenantFormDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Present -> edit this tenant. Absent/null -> create a new tenant. */
  tenant?: TenantSummary | null
  onCreated?: (tenant: CreatedTenant) => void
  onUpdated?: (tenant: TenantSummary) => void
}

/**
 * Dialog-based tenant create/edit form, callable from a kebab menu (edit)
 * on both the tenant overview and detail page, or from the overview's
 * "Nieuwe tenant" action (create) — see KOZ-27. Fully controlled: the
 * caller owns `open` state and supplies the tenant being edited (or
 * `null`/omitted for create).
 */
export function TenantFormDialog({ open, onOpenChange, tenant, onCreated, onUpdated }: TenantFormDialogProps) {
  const isEdit = tenant != null

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{isEdit ? "Tenant bewerken" : "Nieuwe tenant"}</DialogTitle>
          <DialogDescription>
            {isEdit
              ? `Wijzig de gegevens van "${tenant.name}".`
              : "Maak een nieuwe tenant aan. Er wordt automatisch een tenant-beheerder voor aangemaakt."}
          </DialogDescription>
        </DialogHeader>
        {isEdit ? (
          <ConfigForm
            key={tenant.subdomain}
            fields={fields}
            schema={tenantFormSchema}
            defaultValues={{ name: tenant.name, slug: tenant.subdomain }}
            action={(payload) => updateTenant(tenant.subdomain, payload)}
            onSuccess={(result) => {
              if (result) {
                onOpenChange(false)
                onUpdated?.(result)
              }
            }}
            submitLabel="Wijzigingen opslaan"
            pendingLabel="Bezig met opslaan..."
          />
        ) : (
          <ConfigForm
            key="create"
            fields={fields}
            schema={tenantFormSchema}
            defaultValues={{ name: "", slug: "" }}
            action={createTenant}
            onSuccess={(result) => {
              if (result) {
                onOpenChange(false)
                onCreated?.(result)
              }
            }}
            submitLabel="Tenant aanmaken"
            pendingLabel="Bezig met aanmaken..."
          />
        )}
      </DialogContent>
    </Dialog>
  )
}
