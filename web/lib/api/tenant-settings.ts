import { apiBaseUrl } from "@/lib/api-base-url"
import { getClientLocale, type Locale } from "@/lib/i18n/locale"
import { translate } from "@/lib/i18n/translate"
import type { TenantLocaleFormValues } from "@/lib/schemas/tenant-settings"
import type { ActionResult } from "@/lib/forms/types"

import { getJsonOrThrow, requestAction } from "./http"

/**
 * KOZ-34: the current tenant's settings (default locale + whether a login
 * image has been uploaded), as returned by GET /api/settings — guarded
 * backend-side by ROLE_TENANT_ADMIN (see GetTenantSettingsController).
 */
export type TenantSettings = { defaultLocale: Locale; hasLoginImage: boolean }

function tenantSettingsSaveFailedMessage(): string {
  return translate("tenantSettings.error.saveFailed", getClientLocale()) ?? "Failed to save settings."
}

/**
 * Tenant settings page (KOZ-34): GET /api/settings. Throws on a non-OK
 * response, same convention as `listTenants`/`listTenantUsers` — the
 * calling page renders an error state rather than a silently empty form.
 */
export async function getTenantSettings(): Promise<TenantSettings> {
  return getJsonOrThrow("/api/settings", "tenant settings")
}

/**
 * Tenant settings page "default language" section (KOZ-34): PATCH
 * /api/settings/locale, guarded backend-side by ROLE_TENANT_ADMIN. Backend
 * failures (unsupported locale) come back as a single `{ message, errorKey }`,
 * attached to `defaultLocale` here so `<ConfigForm>` shows it in the right
 * place.
 */
export async function updateTenantDefaultLocale(
  values: TenantLocaleFormValues
): Promise<ActionResult<TenantSettings>> {
  return requestAction("/api/settings/locale", {
    method: "PATCH",
    json: values,
    fallbackMessage: tenantSettingsSaveFailedMessage,
    errorField: "defaultLocale",
  })
}

/**
 * Tenant settings page "login image" section (KOZ-34): POST
 * /api/settings/login-image (multipart/form-data, field name "image"),
 * guarded backend-side by ROLE_TENANT_ADMIN (see
 * UploadTenantLoginImageController). Mirrors the upload-failure mapping the
 * other tenant-settings actions use, attached to a `loginImage`
 * pseudo-field since this isn't driven by `<ConfigForm>`.
 */
export async function uploadTenantLoginImage(file: File): Promise<ActionResult<TenantSettings>> {
  const formData = new FormData()
  formData.append("image", file)

  return requestAction("/api/settings/login-image", {
    formData,
    fallbackMessage: tenantSettingsSaveFailedMessage,
    errorField: "loginImage",
  })
}

/**
 * Fetches the current tenant's login-screen image (KOZ-34) and returns a
 * local object URL for it, or `null` when none has been uploaded (404) or
 * the request otherwise fails — see GetTenantLoginImageController, public,
 * no authenticated session required.
 *
 * Deliberately `fetch()` (a real CORS request, always sending an `Origin`
 * header), never a plain `<img src="…api.<base>/api/login-image">`: this
 * app's tenant resolution for the api.<base> hostname
 * (App\Tenancy\Infrastructure\TenantResolverListener::subdomainFromOrigin)
 * depends on that `Origin` header to know which tenant to resolve, and
 * browsers omit it for a plain same-site `<img>` load — verified by hand
 * while building this feature: without this, Chrome's ORB (Opaque Response
 * Blocking) blocks the image outright (net::ERR_BLOCKED_BY_ORB) and, even
 * disregarding that, the backend has no Origin to resolve the tenant from
 * and 404s. `fetch()` doesn't have either problem: it always sends Origin
 * (so CorsListener answers with the right Access-Control-* headers and
 * TenantResolverListener resolves the right tenant), and reading its body
 * as a blob is a real (non-opaque) CORS response, immune to ORB.
 *
 * Caller owns the returned URL's lifetime — revoke it with
 * `URL.revokeObjectURL()` once no longer displayed (e.g. in a `useEffect`
 * cleanup) to avoid leaking blob memory.
 */
export async function fetchTenantLoginImageUrl(): Promise<string | null> {
  const url = `${apiBaseUrl(window.location.host, window.location.protocol)}/api/login-image`

  let response: Response
  try {
    response = await fetch(url)
  } catch {
    return null
  }

  if (!response.ok) {
    return null
  }

  const blob = await response.blob()
  return URL.createObjectURL(blob)
}
