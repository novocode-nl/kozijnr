"use client"

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
  if (!credentials) {
    return null
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Tenant-beheerder aangemaakt</DialogTitle>
          <DialogDescription>
            Bewaar dit wachtwoord nu — het wordt hierna niet meer getoond.
          </DialogDescription>
        </DialogHeader>
        <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 rounded-md bg-muted p-3 font-mono text-sm">
          <dt className="text-muted-foreground">E-mail</dt>
          <dd>{credentials.email}</dd>
          <dt className="text-muted-foreground">Wachtwoord</dt>
          <dd>{credentials.password}</dd>
        </dl>
        <DialogFooter>
          <Button onClick={() => onOpenChange(false)}>Sluiten</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
