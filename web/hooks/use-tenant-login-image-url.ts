import * as React from "react"

import { fetchTenantLoginImageUrl } from "@/lib/api"

/**
 * Loads the current tenant's login-screen image (KOZ-34) as a local object
 * URL, or `undefined` while loading / when none exists — shared by
 * LoginForm (the login screen itself) and the tenant settings page's
 * preview.
 *
 * `version` lets a caller force a refetch after uploading a new image (the
 * settings page bumps it on every successful upload) — otherwise this only
 * ever fetches once per mount.
 *
 * Revokes the previous object URL on cleanup/refetch so blob memory isn't
 * leaked across re-fetches.
 */
export function useTenantLoginImageUrl(version = 0): string | undefined {
  const [url, setUrl] = React.useState<string>()

  React.useEffect(() => {
    let cancelled = false
    let objectUrl: string | null = null

    fetchTenantLoginImageUrl().then((fetchedUrl) => {
      if (cancelled) {
        if (fetchedUrl) URL.revokeObjectURL(fetchedUrl)
        return
      }
      objectUrl = fetchedUrl
      setUrl(fetchedUrl ?? undefined)
    })

    return () => {
      cancelled = true
      if (objectUrl) URL.revokeObjectURL(objectUrl)
    }
  }, [version])

  return url
}
