import { getClientLocale } from "@/lib/i18n/locale"
import { translate } from "@/lib/i18n/translate"
import type { ActionResult } from "@/lib/forms/types"

import { backendUrl, requestAction } from "./http"

/** Response shape from POST /api/me/profile-photo (and, if ever added, its GET metadata counterpart). */
export type ProfilePhotoMeta = {
  id: number
  mimeType: string
  sizeInBytes: number
  originalFilename: string
  uploadedAt: string
}

function profilePhotoUploadFailedMessage(): string {
  return translate("profilePhoto.error.uploadFailed", getClientLocale()) ?? "Failed to upload profile photo."
}

/**
 * KOZ-33 profile page: uploads a profile photo for the logged-in tenant
 * user via POST /api/me/profile-photo (see
 * App\ProfilePhoto\Infrastructure\Controller\UploadTenantProfilePhotoController),
 * the tenant-realm counterpart of KOZ-32's admin upload endpoint. Sends a
 * multipart/form-data body with a single "photo" field, matching the
 * backend's `$request->files->get('photo')` — deliberately not JSON, and
 * deliberately no manual `Content-Type` header (the browser sets the
 * multipart boundary itself).
 */
export async function uploadProfilePhoto(file: File): Promise<ActionResult<ProfilePhotoMeta>> {
  const formData = new FormData()
  formData.append("photo", file)

  return requestAction("/api/me/profile-photo", {
    formData,
    fallbackMessage: profilePhotoUploadFailedMessage,
  })
}

/**
 * Fetches the logged-in tenant user's own profile photo as a Blob (GET
 * /api/me/profile-photo — raw bytes, not JSON, same as the admin
 * counterpart) so the page can build an object URL for `<AvatarImage>`.
 * Returns `null` both when none has been uploaded yet (404) and on any
 * other failure — the page falls back to showing initials either way, the
 * same "don't crash the page" reasoning as `getMe`.
 */
export async function getProfilePhotoBlob(): Promise<Blob | null> {
  let response: Response
  try {
    response = await fetch(backendUrl("/api/me/profile-photo"), {
      method: "GET",
      credentials: "include",
    })
  } catch {
    return null
  }

  if (!response.ok) {
    return null
  }

  return response.blob()
}
