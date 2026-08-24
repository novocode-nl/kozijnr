"use client"

import { useTranslation } from "react-i18next"

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Button } from "@/components/ui/button"
import type { TenantAdminCredentials } from "@/lib/api"

interface TenantAdminCredentialsDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  credentials: TenantAdminCredentials | null
}

/**
 * Shown once, right after a tenant is created: the automatically-created
 * tenant-admin account's generated password only ever exists in this
 * response (only its hash is persisted server-side), so this is the one
 * chance to hand it to the super admin.
 */
export function TenantAdminCredentialsDialog({
  open,
  onOpenChange,
  credentials,
}: TenantAdminCredentialsDialogProps) {
  const { t } = useTranslation()

  if (!credentials) {
    return null
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("tenants.adminCredentialsTitle")}</DialogTitle>
          <DialogDescription>{t("tenants.adminCredentialsDescription")}</DialogDescription>
        </DialogHeader>
        <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 rounded-md bg-muted p-3 font-mono text-sm">
          <dt className="text-muted-foreground">{t("tenants.adminCredentialsEmailLabel")}</dt>
          <dd>{credentials.email}</dd>
          <dt className="text-muted-foreground">{t("tenants.adminCredentialsPasswordLabel")}</dt>
          <dd>{credentials.password}</dd>
        </dl>
        <DialogFooter>
          <Button onClick={() => onOpenChange(false)}>{t("common.close")}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
