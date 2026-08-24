"use client"

import * as React from "react"
import { useTranslation } from "react-i18next"

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { archiveTenant, unarchiveTenant, type TenantSummary } from "@/lib/api"

interface ArchiveTenantDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  tenant: TenantSummary | null
  onChanged: (tenant: TenantSummary) => void
}

/**
 * Confirmation dialog for both archiving and unarchiving a tenant (KOZ-27)
 * — the direction is inferred from `tenant.archived`, so one component
 * covers the kebab menu's "Archiveren" action (overview + detail page) and
 * the archived-overview's "Dearchiveren" action. Archiving is destructive
 * enough (an archived tenant's users can no longer log in) to warrant this
 * warning step; unarchiving reuses the same dialog for consistency even
 * though it's reversible.
 */
export function ArchiveTenantDialog({ open, onOpenChange, tenant, onChanged }: ArchiveTenantDialogProps) {
  const { t } = useTranslation()
  const [isSubmitting, setIsSubmitting] = React.useState(false)
  const [error, setError] = React.useState<string | null>(null)

  if (!tenant) {
    return null
  }

  const willArchive = !tenant.archived

  async function handleConfirm() {
    if (!tenant) {
      return
    }

    setIsSubmitting(true)
    setError(null)

    const result = willArchive ? await archiveTenant(tenant.subdomain) : await unarchiveTenant(tenant.subdomain)

    setIsSubmitting(false)

    if (!result.success) {
      setError(result.message ?? t("common.genericError"))
      return
    }

    onOpenChange(false)
    if (result.data) {
      onChanged(result.data)
    }
  }

  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{willArchive ? t("tenants.archiveTitle") : t("tenants.unarchiveTitle")}</AlertDialogTitle>
          <AlertDialogDescription>
            {willArchive
              ? t("tenants.archiveDescription", { name: tenant.name })
              : t("tenants.unarchiveDescription", { name: tenant.name })}
          </AlertDialogDescription>
        </AlertDialogHeader>
        {error && (
          <div role="alert" className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
            {error}
          </div>
        )}
        <AlertDialogFooter>
          <AlertDialogCancel disabled={isSubmitting}>{t("common.cancel")}</AlertDialogCancel>
          <AlertDialogAction
            variant={willArchive ? "destructive" : "default"}
            onClick={handleConfirm}
            disabled={isSubmitting}
          >
            {isSubmitting ? t("common.busy") : willArchive ? t("tenants.archiveConfirm") : t("tenants.unarchiveConfirm")}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
