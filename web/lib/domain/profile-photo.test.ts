import { describe, expect, it } from "vitest"

import { PROFILE_PHOTO_MAX_SIZE_IN_BYTES, validateProfilePhotoFile } from "@/lib/domain/profile-photo"

describe("validateProfilePhotoFile", () => {
  it("accepts a valid JPEG", () => {
    expect(validateProfilePhotoFile({ type: "image/jpeg", size: 1024 })).toBeNull()
  })

  it("accepts a valid PNG", () => {
    expect(validateProfilePhotoFile({ type: "image/png", size: 1024 })).toBeNull()
  })

  it("accepts a valid WEBP", () => {
    expect(validateProfilePhotoFile({ type: "image/webp", size: 1024 })).toBeNull()
  })

  it("rejects an unsupported mime type", () => {
    expect(validateProfilePhotoFile({ type: "application/pdf", size: 1024 })).toBe("unsupportedMimeType")
  })

  it("rejects an empty file", () => {
    expect(validateProfilePhotoFile({ type: "image/png", size: 0 })).toBe("empty")
  })

  it("rejects a file over the 5MB limit", () => {
    expect(
      validateProfilePhotoFile({ type: "image/png", size: PROFILE_PHOTO_MAX_SIZE_IN_BYTES + 1 })
    ).toBe("tooLarge")
  })

  it("accepts a file exactly at the 5MB limit", () => {
    expect(validateProfilePhotoFile({ type: "image/png", size: PROFILE_PHOTO_MAX_SIZE_IN_BYTES })).toBeNull()
  })
})
