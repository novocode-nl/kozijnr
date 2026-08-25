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
import type { CreatedTenantUser } from "@/lib/api"
import { roleLabel } from "@/lib/i18n/role-labels"

interface TenantUserCredentialsDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  credentials: CreatedTenantUser | null
}

/**
 * Shown once, right after a tenant user is created from the "Gebruikers"
 * tab (KOZ-31): the generated password only ever exists in the create
 * response (only its hash is persisted server-side), so this is the one
 * chance to hand it to the super admin. Same one-time-credentials pattern
 * as TenantAdminCredentialsDialog (KOZ-27), generalized to any role rather
 * than always "tenant-beheerder" — the role actually chosen is shown too.
 */
export function TenantUserCredentialsDialog({
  open,
  onOpenChange,
  credentials,
}: TenantUserCredentialsDialogProps) {
  const { t } = useTranslation()

  if (!credentials) {
    return null
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("users.credentialsTitle")}</DialogTitle>
          <DialogDescription>{t("users.credentialsDescription")}</DialogDescription>
        </DialogHeader>
        <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 rounded-md bg-muted p-3 font-mono text-sm">
          <dt className="text-muted-foreground">{t("users.credentialsEmailLabel")}</dt>
          <dd>{credentials.email}</dd>
          <dt className="text-muted-foreground">{t("users.credentialsRoleLabel")}</dt>
          <dd>{credentials.roles.map((role) => roleLabel(role, t)).join(", ")}</dd>
          <dt className="text-muted-foreground">{t("users.credentialsPasswordLabel")}</dt>
          <dd>{credentials.password}</dd>
        </dl>
        <DialogFooter>
          <Button onClick={() => onOpenChange(false)}>{t("common.close")}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
