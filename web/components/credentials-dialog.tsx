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
import { roleLabel } from "@/lib/i18n/role-labels"

export type Credentials = { email: string; password: string; roles?: string[] }

interface CredentialsDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  credentials: Credentials | null
  /** i18n-keyprefix: `${prefix}Title|Description|EmailLabel|PasswordLabel` en, bij roles, `${prefix}RoleLabel`. */
  i18nPrefix: "users.adminCredentials" | "tenants.adminCredentials" | "users.credentials"
}

/**
 * One-time credentials dialog: shown right after an account with a
 * generated password is created (tenant admin on tenant create, KOZ-27;
 * tenant user, KOZ-31; admin user, KOZ-30). The password only ever exists
 * in that one response (only its hash is persisted server-side), so this
 * is the single chance to hand it over. The i18nPrefix keeps each flow's
 * existing catalog keys; a roles row renders only when the caller has
 * roles to show.
 */
export function CredentialsDialog({ open, onOpenChange, credentials, i18nPrefix }: CredentialsDialogProps) {
  const { t } = useTranslation()

  if (!credentials) {
    return null
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t(`${i18nPrefix}Title`)}</DialogTitle>
          <DialogDescription>{t(`${i18nPrefix}Description`)}</DialogDescription>
        </DialogHeader>
        <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 rounded-md bg-muted p-3 font-mono text-sm">
          <dt className="text-muted-foreground">{t(`${i18nPrefix}EmailLabel`)}</dt>
          <dd>{credentials.email}</dd>
          {credentials.roles !== undefined && (
            <>
              <dt className="text-muted-foreground">{t(`${i18nPrefix}RoleLabel`)}</dt>
              <dd>{credentials.roles.map((role) => roleLabel(role, t)).join(", ")}</dd>
            </>
          )}
          <dt className="text-muted-foreground">{t(`${i18nPrefix}PasswordLabel`)}</dt>
          <dd>{credentials.password}</dd>
        </dl>
        <DialogFooter>
          <Button onClick={() => onOpenChange(false)}>{t("common.close")}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
