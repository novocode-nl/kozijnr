import { AdminLoginForm } from "@/components/admin-login-form"
import { LoginForm } from "@/components/login-form"
import { getAppContext } from "@/lib/context/app-context"

/**
 * Unified login page: a single `/login` path for both the tenant and admin
 * realms — the Host header alone (lib/context/app-context.ts) decides which
 * form renders, never the URL path. Kept as a Server Component so it can
 * read the Host header before rendering; the form components stay client
 * components.
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
