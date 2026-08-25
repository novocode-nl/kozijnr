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
import type { AdminUserCredentials } from "@/lib/api"

interface AdminUserCredentialsDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  credentials: AdminUserCredentials | null
}

/**
 * Shown once, right after a new admin user is created (KOZ-30): the
 * generated password only ever exists in that response (only its hash is
 * persisted server-side), so this is the one chance to hand it to the
 * super admin who created the account. Modeled directly on
 * `<TenantAdminCredentialsDialog>` (KOZ-27).
 */
export function AdminUserCredentialsDialog({
  open,
  onOpenChange,
  credentials,
}: AdminUserCredentialsDialogProps) {
  const { t } = useTranslation()

  if (!credentials) {
    return null
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("users.adminCredentialsTitle")}</DialogTitle>
          <DialogDescription>{t("users.adminCredentialsDescription")}</DialogDescription>
        </DialogHeader>
        <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 rounded-md bg-muted p-3 font-mono text-sm">
          <dt className="text-muted-foreground">{t("users.adminCredentialsEmailLabel")}</dt>
          <dd>{credentials.email}</dd>
          <dt className="text-muted-foreground">{t("users.adminCredentialsPasswordLabel")}</dt>
          <dd>{credentials.password}</dd>
        </dl>
        <DialogFooter>
          <Button onClick={() => onOpenChange(false)}>{t("common.close")}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
