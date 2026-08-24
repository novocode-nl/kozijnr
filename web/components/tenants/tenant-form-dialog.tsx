"use client"

import { useMemo } from "react"
import { useTranslation } from "react-i18next"

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { ConfigForm } from "@/components/config-form/config-form"
import { createTenant, updateTenant, type CreatedTenant, type TenantSummary } from "@/lib/api"
import {
  buildCreateTenantFormSchema,
  buildTenantFormSchema,
  type CreateTenantFormValues,
  type TenantFormValues,
} from "@/lib/schemas/tenant"
import type { FieldConfig } from "@/lib/forms/types"
import type { Locale } from "@/lib/i18n/locale"

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
  const { t, i18n } = useTranslation()
  const locale = i18n.language as Locale
  const tenantSchema = useMemo(() => buildTenantFormSchema(locale), [locale])
  const createSchema = useMemo(() => buildCreateTenantFormSchema(locale), [locale])

  /**
   * Shared `name`/`slug` field config for both the create and edit tenant
   * dialogs (KOZ-27, replacing KOZ-25's page-based create/edit flow). Form
   * values already match `TenantPayload` 1:1, so neither ConfigForm
   * instantiation below needs a `transformSubmit`.
   */
  const editFields: FieldConfig<TenantFormValues>[] = [
    {
      name: "name",
      label: t("tenantForm.nameLabel"),
      type: "text",
      placeholder: "Acme B.V.",
      autoComplete: "off",
    },
    {
      name: "slug",
      label: t("tenantForm.slugLabel"),
      type: "text",
      placeholder: "acme",
      hint: t("tenantForm.slugHint"),
      autoComplete: "off",
    },
  ]

  /**
   * Create-only fields: the `name`/`slug` pair plus the tenant-admin's email
   * (KOZ-27 rework) — the operator now chooses this address instead of it
   * being auto-generated from the subdomain. Only relevant when there's an
   * admin account to create in the first place, i.e. create, not edit.
   */
  const createFields: FieldConfig<CreateTenantFormValues>[] = [
    ...editFields,
    {
      name: "adminEmail",
      label: t("tenantForm.adminEmailLabel"),
      type: "email",
      placeholder: "beheerder@acme.nl",
      hint: t("tenantForm.adminEmailHint"),
      autoComplete: "off",
    },
  ]

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{isEdit ? t("tenantForm.editTitle") : t("tenantForm.createTitle")}</DialogTitle>
          <DialogDescription>
            {isEdit
              ? t("tenantForm.editDescription", { name: tenant.name })
              : t("tenantForm.createDescription")}
          </DialogDescription>
        </DialogHeader>
        {isEdit ? (
          <ConfigForm
            key={tenant.subdomain}
            fields={editFields}
            schema={tenantSchema}
            defaultValues={{ name: tenant.name, slug: tenant.subdomain }}
            action={(payload) => updateTenant(tenant.subdomain, payload)}
            onSuccess={(result) => {
              if (result) {
                onOpenChange(false)
                onUpdated?.(result)
              }
            }}
            submitLabel={t("tenantForm.saveChanges")}
            pendingLabel={t("tenantForm.savingChanges")}
          />
        ) : (
          <ConfigForm
            key="create"
            fields={createFields}
            schema={createSchema}
            defaultValues={{ name: "", slug: "", adminEmail: "" }}
            action={createTenant}
            onSuccess={(result) => {
              if (result) {
                onOpenChange(false)
                onCreated?.(result)
              }
            }}
            submitLabel={t("tenantForm.createSubmit")}
            pendingLabel={t("tenantForm.creating")}
          />
        )}
      </DialogContent>
    </Dialog>
  )
}
