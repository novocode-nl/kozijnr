import { AdminLoginForm } from "@/components/admin-login-form"
import { LoginForm } from "@/components/login-form"
import { getAppContext } from "@/lib/context/app-context"

/**
 * Unified login page (KOZ-14 rework, round 5). A single `/login` path for
 * both the tenant and admin realms — the same pattern already established
 * for the authenticated home route (app/(app)/layout.tsx): the Host header
 * alone (lib/context/app-context.ts) decides which form renders, never the
 * URL path. There is no separate `/admin/login` route anymore — the earlier
 * placeholder page (which only explained a form wasn't built yet) is
 * removed, and proxy.ts's guard redirects both contexts here.
 *
 * A Server Component (not "use client") purely so it can read the Host
 * header via `getAppContext()` before rendering — the two form components
 * themselves stay client components, unchanged in their own right.
 */
export default async function LoginPage() {
  const context = await getAppContext()

  return (
    <div className="flex min-h-svh flex-col items-center justify-center bg-muted p-6 md:p-10">
      <div className="w-full max-w-sm md:max-w-4xl">
        {context === "admin" ? <AdminLoginForm /> : <LoginForm />}
      </div>
    </div>
  )
}
