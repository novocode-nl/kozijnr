/**
 * KOZ-14 rework (round 3): the redirect target proxy.ts sends an
 * unauthenticated request to on the admin subdomain, once every admin
 * route (not just /dashboard) started requiring a valid super-admin
 * session.
 *
 * *** Deliberately a placeholder, not a working login form ***
 * KOZ-8 shipped the super-admin session itself (backend-only, via
 * POST /api/admin/login on the admin subdomain — see
 * api/src/User/Infrastructure/Controller/AdminLoginController.php) but no
 * frontend UI to drive it: there is no admin-facing equivalent of
 * components/login-form.tsx yet, and building one is explicitly out of
 * this ticket's scope (the ticket asks for the *guard*, not the login
 * screen — that's a separate ticket). Without this page, proxy.ts would
 * have nowhere valid to redirect an unauthenticated admin request to,
 * which would either 404 or bounce back into the tenant /login page
 * (wrong screen — that one posts to /api/login, the tenant endpoint,
 * which doesn't even exist on the admin subdomain per
 * App\TenantUser\Infrastructure\TenantRouteGuardListener).
 *
 * This page exists so the guard has a real, honest destination to land
 * on: it tells a human they're in the right place (the admin login) and
 * that the form isn't built yet, rather than the guard silently 404ing or
 * redirecting somewhere misleading. Swap this out for the real form (the
 * same react-hook-form + Zod + shadcn pattern components/login-form.tsx
 * already established for tenants, posting to a new
 * app/api/admin/login/route.ts proxy) in that follow-up ticket — nothing
 * else in this guard needs to change when that happens, since proxy.ts
 * only cares about the *path* "/admin/login", not what renders there.
 */
export default function AdminLoginPage() {
  return (
    <div className="flex min-h-svh flex-col items-center justify-center gap-2 bg-muted p-6 text-center md:p-10">
      <h1 className="text-2xl font-bold">Admin-login</h1>
      <p className="max-w-sm text-muted-foreground">
        Het inlogscherm voor beheerders is nog niet gebouwd. Je bent hier
        omdat er geen geldige beheerderssessie is gevonden.
      </p>
    </div>
  )
}
