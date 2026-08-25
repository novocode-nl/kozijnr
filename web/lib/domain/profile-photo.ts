/**
 * Client-side mirror of the allowlist/size-limit enforced server-side in
 * App\ProfilePhoto\Application\UploadProfilePhoto (KOZ-32/KOZ-33). This is
 * a UX nicety only — the backend re-validates independently and is the
 * actual source of truth (kozijnr-backend convention: file validation
 * belongs in the command handler, never trusted from the client) — so
 * catching an obviously-wrong file here just avoids a round trip.
 */
export const PROFILE_PHOTO_ALLOWED_MIME_TYPES = ["image/jpeg", "image/png", "image/webp"] as const

export type ProfilePhotoAllowedMimeType = (typeof PROFILE_PHOTO_ALLOWED_MIME_TYPES)[number]

/** Mirrors UploadProfilePhoto::MAX_SIZE_IN_BYTES (5 MiB). */
export const PROFILE_PHOTO_MAX_SIZE_IN_BYTES = 5 * 1024 * 1024

/**
 * Matches the backend's `profilePhoto.error.*` errorKey suffixes
 * (lib/i18n/resources/{nl,en}.json's `profilePhoto.error` namespace), so a
 * client-side rejection and a server-side rejection show the exact same
 * message.
 */
export type ProfilePhotoValidationError = "unsupportedMimeType" | "empty" | "tooLarge"

export function validateProfilePhotoFile(file: { type: string; size: number }): ProfilePhotoValidationError | null {
  if (!PROFILE_PHOTO_ALLOWED_MIME_TYPES.includes(file.type as ProfilePhotoAllowedMimeType)) {
    return "unsupportedMimeType"
  }

  if (file.size === 0) {
    return "empty"
  }

  if (file.size > PROFILE_PHOTO_MAX_SIZE_IN_BYTES) {
    return "tooLarge"
  }

  return null
}
