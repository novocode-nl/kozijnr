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
import { createAdminUser, type CreatedAdminUser } from "@/lib/api"
import { buildCreateAdminUserFormSchema, type CreateAdminUserFormValues } from "@/lib/schemas/admin-user"
import type { FieldConfig } from "@/lib/forms/types"
import type { Locale } from "@/lib/i18n/locale"

interface AdminUserFormDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onCreated?: (user: CreatedAdminUser) => void
}

/**
 * Dialog-based admin-user create form (KOZ-30), opened from the admin user
 * overview's "Nieuwe gebruiker" action. Every user created this way gets
 * ROLE_SUPER_ADMIN — role selection is out of scope for KOZ-30, same as the
 * `super-admin:create` CLI command it complements. Modeled directly on
 * `<TenantFormDialog>`'s create branch (KOZ-27).
 */
export function AdminUserFormDialog({ open, onOpenChange, onCreated }: AdminUserFormDialogProps) {
  const { t, i18n } = useTranslation()
  const locale = i18n.language as Locale
  const schema = useMemo(() => buildCreateAdminUserFormSchema(locale), [locale])

  const fields: FieldConfig<CreateAdminUserFormValues>[] = [
    {
      name: "email",
      label: t("userForm.emailLabel"),
      type: "email",
      placeholder: "admin@kozijnr.nl",
      hint: t("userForm.emailHint"),
      autoComplete: "off",
    },
  ]

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("userForm.createTitle")}</DialogTitle>
          <DialogDescription>{t("userForm.createDescription")}</DialogDescription>
        </DialogHeader>
        <ConfigForm
          key="create"
          fields={fields}
          schema={schema}
          defaultValues={{ email: "" }}
          action={createAdminUser}
          onSuccess={(result) => {
            if (result) {
              onOpenChange(false)
              onCreated?.(result)
            }
          }}
          submitLabel={t("userForm.createSubmit")}
          pendingLabel={t("userForm.creating")}
        />
      </DialogContent>
    </Dialog>
  )
}
