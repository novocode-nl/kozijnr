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
import type { CreatedTenantUser } from "@/lib/api"
import { buildCreateTenantUserFormSchema, type CreateTenantUserFormValues } from "@/lib/schemas/tenant-user"
import { TenantUser } from "@/lib/domain/tenant-user-roles"
import { roleLabel } from "@/lib/i18n/role-labels"
import type { FieldConfig, FormAction } from "@/lib/forms/types"
import type { Locale } from "@/lib/i18n/locale"

interface TenantUserFormDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  /**
   * Submit action: `createTenantUser` bound to a subdomain for the admin
   * "Gebruikers" tab, or `createOwnTenantUser` for the tenant-own
   * self-service "Gebruikers" page (KOZ-31 rework) — the tenant context
   * for that flow comes from the logged-in session, never a prop here.
   */
  action: FormAction<CreateTenantUserFormValues, CreatedTenantUser>
  onCreated?: (user: CreatedTenantUser) => void
}

/**
 * "Gebruiker toevoegen" dialog (KOZ-31, generalized in the KOZ-31 rework to
 * also back the tenant-own self-service "Gebruikers" page, not just the
 * admin detail page's "Gebruikers" tab): create an additional tenant user
 * for the current tenant, with a choice between the two roles that already
 * exist on TenantUser (ROLE_TENANT_ADMIN / ROLE_TENANT_USER). Mirrors
 * TenantFormDialog's create-only shape: fully controlled, single
 * ConfigForm instantiation. Which endpoint the submit hits is entirely the
 * caller's `action` prop's concern — this component only knows the form
 * shape.
 */
export function TenantUserFormDialog({ open, onOpenChange, action, onCreated }: TenantUserFormDialogProps) {
  const { t, i18n } = useTranslation()
  const locale = i18n.language as Locale
  const schema = useMemo(() => buildCreateTenantUserFormSchema(locale), [locale])

  const fields: FieldConfig<CreateTenantUserFormValues>[] = [
    {
      name: "email",
      label: t("users.userForm.emailLabel"),
      type: "email",
      placeholder: "collega@acme.nl",
      autoComplete: "off",
    },
    {
      name: "role",
      label: t("users.userForm.roleLabel"),
      type: "select",
      options: [
        { value: TenantUser.ROLE_TENANT_ADMIN, label: roleLabel(TenantUser.ROLE_TENANT_ADMIN, t) },
        { value: TenantUser.ROLE_TENANT_USER, label: roleLabel(TenantUser.ROLE_TENANT_USER, t) },
      ],
    },
  ]

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("users.userForm.title")}</DialogTitle>
          <DialogDescription>{t("users.userForm.description")}</DialogDescription>
        </DialogHeader>
        <ConfigForm
          fields={fields}
          schema={schema}
          defaultValues={{ email: "", role: TenantUser.ROLE_TENANT_USER }}
          action={action}
          onSuccess={(result) => {
            if (result) {
              onOpenChange(false)
              onCreated?.(result)
            }
          }}
          submitLabel={t("users.userForm.createSubmit")}
          pendingLabel={t("users.userForm.creating")}
        />
      </DialogContent>
    </Dialog>
  )
}
