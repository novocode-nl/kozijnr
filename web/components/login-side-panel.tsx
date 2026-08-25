"use client"

import { useState } from "react"

const PLACEHOLDER_IMAGE_URL = "https://ui.shadcn.com/placeholder.svg"

/**
 * Decorative image side panel, shared by the tenant and admin login forms.
 * Points at shadcn's hosted placeholder rather than a local public/ asset,
 * because proxy.ts's auth guard would redirect unauthenticated requests for
 * static assets under /login back to /login itself.
 *
 * KOZ-34: `imageUrl`, when given (LoginForm passes the tenant's public
 * GET /api/login-image URL — see lib/api.ts's `tenantLoginImageUrl`),
 * overrides the placeholder. Falls back to the placeholder on load failure
 * (no login image uploaded for this tenant yet -> 404) via `onError`,
 * rather than a pre-check request, so there's no extra round trip before
 * the panel can render anything at all. AdminLoginForm never passes
 * `imageUrl` — the admin realm has no tenant, so it always shows the
 * placeholder (KOZ-34 is tenant-settings-only, out of scope for admin).
 */
export function LoginSidePanel({ imageUrl }: { imageUrl?: string }) {
  const [failed, setFailed] = useState(false)

  return (
    <div className="relative hidden bg-muted md:block">
      <img
        src={imageUrl && !failed ? imageUrl : PLACEHOLDER_IMAGE_URL}
        alt=""
        onError={() => setFailed(true)}
        className="absolute inset-0 h-full w-full object-cover dark:brightness-[0.2] dark:grayscale"
      />
    </div>
  )
}
