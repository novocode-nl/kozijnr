"use client"

import * as React from "react"
import { useTranslation } from "react-i18next"

import { PageHeading } from "@/components/page-heading"
import { ConfigForm } from "@/components/config-form/config-form"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Skeleton } from "@/components/ui/skeleton"
import { contextLabel } from "@/lib/navigation/menu-config"
import { SUPPORTED_LOCALES, type Locale } from "@/lib/i18n/locale"
import { buildTenantLocaleFormSchema, type TenantLocaleFormValues } from "@/lib/schemas/tenant-settings"
import { TenantUser } from "@/lib/domain/tenant-user-roles"
import { useTenantLoginImageUrl } from "@/hooks/use-tenant-login-image-url"
import {
  getMe,
  getTenantSettings,
  updateTenantDefaultLocale,
  uploadTenantLoginImage,
  type TenantSettings,
} from "@/lib/api"
import type { FieldConfig } from "@/lib/forms/types"

type LoadState =
  | { status: "loading" }
  | { status: "forbidden" }
  | { status: "error" }
  | { status: "loaded"; settings: TenantSettings }

/**
 * Tenant settings page (KOZ-34): a tenant-admin manages this tenant's
 * login-screen image and default locale here. ROLE_TENANT_ADMIN only —
 * checked here via GET /api/me for UI purposes (same pattern as
 * OwnUsersPage's "Gebruiker toevoegen" gate), while the backend
 * (GetTenantSettingsController / UpdateTenantDefaultLocaleController /
 * UploadTenantLoginImageController) enforces the same role gate
 * independently, so a non-admin can never actually change these settings
 * even by calling the endpoints directly.
 */
export default function TenantSettingsPage() {
  const { t, i18n } = useTranslation()
  const [state, setState] = React.useState<LoadState>({ status: "loading" })
  const [imageVersion, setImageVersion] = React.useState(0)
  const [selectedFile, setSelectedFile] = React.useState<File | null>(null)
  const [uploading, setUploading] = React.useState(false)
  const [uploadError, setUploadError] = React.useState<string | null>(null)
  const [uploadSuccess, setUploadSuccess] = React.useState(false)
  const [localeSaveSuccess, setLocaleSaveSuccess] = React.useState(false)

  React.useEffect(() => {
    let cancelled = false

    getMe()
      .then((me) => {
        if (cancelled) return
        if (!me?.roles.includes(TenantUser.ROLE_TENANT_ADMIN)) {
          setState({ status: "forbidden" })
          return
        }
        return getTenantSettings().then((settings) => {
          if (!cancelled) setState({ status: "loaded", settings })
        })
      })
      .catch(() => {
        if (!cancelled) setState({ status: "error" })
      })

    return () => {
      cancelled = true
    }
  }, [])

  const locale = i18n.language as Locale
  const localeSchema = React.useMemo(() => buildTenantLocaleFormSchema(locale), [locale])

  const localeFields: FieldConfig<TenantLocaleFormValues>[] = [
    {
      name: "defaultLocale",
      label: t("tenantSettings.localeLabel"),
      type: "select",
      options: SUPPORTED_LOCALES.map((value) => ({
        value,
        label: t(value === "nl" ? "common.languageNl" : "common.languageEn"),
      })),
    },
  ]

  async function handleUpload() {
    if (!selectedFile) return

    setUploading(true)
    setUploadError(null)
    setUploadSuccess(false)

    const result = await uploadTenantLoginImage(selectedFile)

    setUploading(false)

    if (!result.success) {
      setUploadError(result.fieldErrors?.loginImage ?? result.message ?? t("tenantSettings.error.uploadFailed"))
      return
    }

    setState({ status: "loaded", settings: result.data ?? { defaultLocale: locale, hasLoginImage: true } })
    setSelectedFile(null)
    setImageVersion((version) => version + 1)
    setUploadSuccess(true)
  }

  return (
    <div className="flex flex-1 flex-col gap-6">
      <PageHeading
        title={t("tenantSettings.pageTitle")}
        description={t("tenantSettings.pageDescription")}
        breadcrumbs={[{ label: contextLabel.tenant, href: "/" }, { label: t("tenantSettings.pageTitle") }]}
      />

      {state.status === "loading" && (
        <div className="flex flex-col gap-2">
          <Skeleton className="h-32 w-full" />
          <Skeleton className="h-32 w-full" />
        </div>
      )}

      {state.status === "forbidden" && (
        <p className="text-sm text-destructive">{t("tenantSettings.accessDenied")}</p>
      )}

      {state.status === "error" && (
        <p className="text-sm text-destructive">{t("tenantSettings.loadError")}</p>
      )}

      {state.status === "loaded" && (
        <div className="grid gap-6 md:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle>{t("tenantSettings.localeSectionTitle")}</CardTitle>
              <CardDescription>{t("tenantSettings.localeSectionDescription")}</CardDescription>
            </CardHeader>
            <CardContent>
              <ConfigForm
                fields={localeFields}
                schema={localeSchema}
                defaultValues={{ defaultLocale: state.settings.defaultLocale }}
                action={updateTenantDefaultLocale}
                onSuccess={(result) => {
                  if (result) {
                    setState({ status: "loaded", settings: result })
                    setLocaleSaveSuccess(true)
                  }
                }}
                submitLabel={t("tenantSettings.localeSave")}
                pendingLabel={t("tenantSettings.localeSaving")}
              />
              {localeSaveSuccess && (
                <p className="mt-2 text-sm text-muted-foreground">{t("tenantSettings.localeSaveSuccess")}</p>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>{t("tenantSettings.loginImageSectionTitle")}</CardTitle>
              <CardDescription>{t("tenantSettings.loginImageSectionDescription")}</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
              {state.settings.hasLoginImage ? (
                <div className="relative h-40 w-full overflow-hidden rounded-lg border">
                  <LoginImagePreview version={imageVersion} alt={t("tenantSettings.loginImagePreviewAlt")} />
                </div>
              ) : (
                <p className="text-sm text-muted-foreground">{t("tenantSettings.loginImageNone")}</p>
              )}

              <Input
                type="file"
                accept="image/jpeg,image/png,image/webp"
                onChange={(event) => {
                  setSelectedFile(event.target.files?.[0] ?? null)
                  setUploadError(null)
                  setUploadSuccess(false)
                }}
              />

              {uploadError && (
                <div role="alert" className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
                  {uploadError}
                </div>
              )}

              {uploadSuccess && (
                <p className="text-sm text-muted-foreground">{t("tenantSettings.loginImageUploadSuccess")}</p>
              )}

              <Button onClick={handleUpload} disabled={!selectedFile || uploading}>
                {uploading ? t("tenantSettings.loginImageUploading") : t("tenantSettings.loginImageUpload")}
              </Button>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  )
}

/**
 * Refetches after a new upload: `version` (bumped by the parent on every
 * successful upload) is `useTenantLoginImageUrl`'s explicit refetch
 * trigger — see hooks/use-tenant-login-image-url.ts for why this fetches
 * the image (rather than a plain `<img src="…/api/login-image">`) and
 * turns it into an object URL.
 */
function LoginImagePreview({ version, alt }: { version: number; alt: string }) {
  const src = useTenantLoginImageUrl(version)

  if (!src) return null

  return (
    // eslint-disable-next-line @next/next/no-img-element -- object URL from a fetched blob; not a static Next asset.
    <img src={src} alt={alt} className="h-full w-full object-cover" />
  )
}
