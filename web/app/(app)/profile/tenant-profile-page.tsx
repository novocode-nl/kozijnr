"use client"

import * as React from "react"
import { useTranslation } from "react-i18next"

import { PageHeading } from "@/components/page-heading"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"
import { contextLabel } from "@/lib/navigation/menu-config"
import { roleLabel } from "@/lib/i18n/role-labels"
import { validateProfilePhotoFile } from "@/lib/domain/profile-photo"
import { getMe, getProfilePhotoBlob, uploadProfilePhoto, type CurrentTenantUser } from "@/lib/api"

type LoadState = { status: "loading" } | { status: "error" } | { status: "loaded"; me: CurrentTenantUser }

/**
 * KOZ-33: the tenant-user's own profile page — shows the read-only
 * email/role(s) the backend's TenantUser has today (GET /api/me, already
 * built for KOZ-31's "Gebruikers" page), plus a profile-photo
 * upload/display, reusing the KOZ-32 ProfilePhoto flow now exposed at
 * /api/me/profile-photo for tenant users (see
 * App\ProfilePhoto\Infrastructure\Controller\{Upload,Get}TenantProfilePhotoController).
 *
 * Authorization is entirely structural, not something this page needs to
 * reason about: both endpoints always resolve the owner from the
 * authenticated session, never from a client-supplied id, so there is no
 * way to view or replace another user's photo.
 */
export default function TenantProfilePage() {
  const { t } = useTranslation()
  const [state, setState] = React.useState<LoadState>({ status: "loading" })
  const [photoUrl, setPhotoUrl] = React.useState<string | null>(null)
  const [photoLoading, setPhotoLoading] = React.useState(true)
  const [uploading, setUploading] = React.useState(false)
  const [uploadError, setUploadError] = React.useState<string | null>(null)
  const fileInputRef = React.useRef<HTMLInputElement>(null)

  React.useEffect(() => {
    let cancelled = false

    getMe()
      .then((me) => {
        if (cancelled) {
          return
        }
        setState(me ? { status: "loaded", me } : { status: "error" })
      })
      .catch(() => {
        if (!cancelled) {
          setState({ status: "error" })
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  React.useEffect(() => {
    let cancelled = false
    let objectUrl: string | null = null

    getProfilePhotoBlob()
      .then((blob) => {
        if (cancelled) {
          return
        }
        if (blob) {
          objectUrl = URL.createObjectURL(blob)
          setPhotoUrl(objectUrl)
        }
        setPhotoLoading(false)
      })
      .catch(() => {
        if (!cancelled) {
          setPhotoLoading(false)
        }
      })

    return () => {
      cancelled = true
      if (objectUrl) {
        URL.revokeObjectURL(objectUrl)
      }
    }
  }, [])

  function handleChooseFile() {
    fileInputRef.current?.click()
  }

  async function handleFileSelected(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0] ?? null
    event.target.value = ""

    if (!file) {
      return
    }

    const validationError = validateProfilePhotoFile(file)
    if (validationError) {
      setUploadError(t(`profilePhoto.error.${validationError}`))
      return
    }

    setUploading(true)
    setUploadError(null)

    const result = await uploadProfilePhoto(file)

    setUploading(false)

    if (!result.success) {
      setUploadError(result.message ?? t("profilePhoto.error.uploadFailed"))
      return
    }

    setPhotoUrl((current) => {
      if (current) {
        URL.revokeObjectURL(current)
      }
      return URL.createObjectURL(file)
    })
  }

  return (
    <div className="flex flex-1 flex-col gap-6">
      <PageHeading
        title={t("profile.pageTitle")}
        breadcrumbs={[{ label: contextLabel.tenant, href: "/" }, { label: t("profile.pageTitle") }]}
      />

      {state.status === "loading" && (
        <div className="flex flex-col gap-2">
          <Skeleton className="h-8 w-full" />
          <Skeleton className="h-8 w-full" />
        </div>
      )}

      {state.status === "error" && <p className="text-sm text-destructive">{t("profile.loadError")}</p>}

      {state.status === "loaded" && (
        <div className="flex flex-col gap-6 sm:flex-row">
          <Card className="sm:w-80">
            <CardHeader>
              <CardTitle>{t("profile.photoSectionTitle")}</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col items-center gap-4">
              {photoLoading ? (
                <Skeleton className="size-24 rounded-full" />
              ) : (
                <Avatar size="lg" className="size-24">
                  {photoUrl ? <AvatarImage src={photoUrl} alt={t("profile.photoAlt")} /> : null}
                  <AvatarFallback>{state.me.email.slice(0, 2).toUpperCase()}</AvatarFallback>
                </Avatar>
              )}

              <input
                ref={fileInputRef}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="hidden"
                onChange={handleFileSelected}
              />

              <Button onClick={handleChooseFile} disabled={uploading} variant="outline">
                {uploading ? t("profile.uploading") : photoUrl ? t("profile.changeButton") : t("profile.uploadButton")}
              </Button>

              <p className="text-center text-xs text-muted-foreground">{t("profile.photoHint")}</p>

              {uploadError && <p className="text-center text-sm text-destructive">{uploadError}</p>}
            </CardContent>
          </Card>

          <Card className="flex-1">
            <CardHeader>
              <CardTitle>{t("profile.detailsSectionTitle")}</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
              <div>
                <p className="text-xs text-muted-foreground">{t("profile.emailLabel")}</p>
                <p className="text-sm font-medium">{state.me.email}</p>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">{t("profile.roleLabel")}</p>
                <p className="text-sm font-medium">
                  {state.me.roles.map((role) => roleLabel(role, t)).join(", ")}
                </p>
              </div>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  )
}
