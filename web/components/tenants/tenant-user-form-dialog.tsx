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
import { createTenantUser, type CreatedTenantUser } from "@/lib/api"
import { buildCreateTenantUserFormSchema, type CreateTenantUserFormValues } from "@/lib/schemas/tenant-user"
import { TenantUser } from "@/lib/domain/tenant-user-roles"
import { roleLabel } from "@/lib/i18n/role-labels"
import type { FieldConfig } from "@/lib/forms/types"
import type { Locale } from "@/lib/i18n/locale"

interface TenantUserFormDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  subdomain: string
  onCreated?: (user: CreatedTenantUser) => void
}

/**
 * "Gebruiker toevoegen" dialog on the tenant detail page's "Gebruikers" tab
 * (KOZ-31): create an additional tenant user for the current tenant, with a
 * choice between the two roles that already exist on TenantUser
 * (ROLE_TENANT_ADMIN / ROLE_TENANT_USER). Mirrors TenantFormDialog's
 * create-only shape: fully controlled, single ConfigForm instantiation.
 */
export function TenantUserFormDialog({ open, onOpenChange, subdomain, onCreated }: TenantUserFormDialogProps) {
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
          key={subdomain}
          fields={fields}
          schema={schema}
          defaultValues={{ email: "", role: TenantUser.ROLE_TENANT_USER }}
          action={(payload) => createTenantUser(subdomain, payload)}
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
