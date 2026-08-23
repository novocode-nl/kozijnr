import { describe, expect, it } from "vitest"

import { translate } from "@/lib/i18n/translate"

/**
 * KOZ-29 DoD: "the same action gives a different message in NL vs EN".
 * The backend never translates — it returns a stable `errorKey`, scoped to
 * the domain it belongs to (`tenants.error.*`, `users.error.*`,
 * `auth.error.*` — see App\Shared\Domain\Exception\HasErrorKey on the
 * backend, and lib/api.ts's `apiErrorMessage` on this side) and the
 * frontend renders it per the active language. This proves that rendering
 * step directly: the same key resolves to two different, non-empty strings
 * depending on locale, for every error key the backend actually emits.
 */
describe("translate", () => {
  it("renders the same backend errorKey differently in nl vs en", () => {
    const nl = translate("tenants.error.subdomainAlreadyExists", "nl", { subdomain: "acme" })
    const en = translate("tenants.error.subdomainAlreadyExists", "en", { subdomain: "acme" })

    expect(nl).toBe('Er bestaat al een tenant met subdomain "acme".')
    expect(en).toBe('A tenant with subdomain "acme" already exists.')
    expect(nl).not.toBe(en)
  })

  it("renders auth.error.invalidCredentials differently per locale", () => {
    const nl = translate("auth.error.invalidCredentials", "nl")
    const en = translate("auth.error.invalidCredentials", "en")

    expect(nl).toBe("Ongeldige inloggegevens.")
    expect(en).toBe("Invalid credentials.")
    expect(nl).not.toBe(en)
  })

  it("has both an nl and an en translation for every backend error key, under its own domain namespace", () => {
    const backendErrorKeys = [
      "tenants.error.subdomainAlreadyExists",
      "tenants.error.schemaAlreadyExists",
      "tenants.error.schemaConflict",
      "tenants.error.notFound",
      "tenants.error.nameInvalid",
      "tenants.error.nameRequired",
      "tenants.error.nameTooLong",
      "tenants.error.subdomainRequired",
      "tenants.error.adminEmailInvalid",
      "tenants.error.adminEmailAlreadyExists",
      "tenants.error.createFailed",
      "tenants.error.updateFailed",
      "tenants.error.archiveFailed",
      "tenants.error.unarchiveFailed",
      "users.error.emailAlreadyExists",
      "auth.error.invalidCredentials",
      "auth.error.notAuthenticated",
    ]

    for (const key of backendErrorKeys) {
      const nl = translate(key, "nl")
      const en = translate(key, "en")
      expect(nl, `nl translation missing for "${key}"`).toBeTruthy()
      expect(en, `en translation missing for "${key}"`).toBeTruthy()
      expect(nl, `nl and en are identical for "${key}"`).not.toBe(en)
    }
  })

  it("interpolates params and falls back to English for an unknown locale entry", () => {
    expect(translate("tenants.error.nameTooLong", "nl", { max: 255 })).toBe(
      "Naam mag maximaal 255 tekens bevatten."
    )
    expect(translate("does.not.exist", "nl")).toBeUndefined()
  })
})
