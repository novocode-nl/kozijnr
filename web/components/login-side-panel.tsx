/**
 * Decorative image side panel for the login-04 two-column layout (KOZ-16),
 * shared by components/login-form.tsx and components/admin-login-form.tsx.
 *
 * Points at shadcn's own hosted placeholder (ui.shadcn.com/placeholder.svg)
 * — an external URL, not a file under this app's public/, since every
 * request under /login (including static assets served from this app's
 * own public/) passes through proxy.ts's auth guard, which redirects
 * unauthenticated requests back to /login itself (see proxy.ts's
 * `matcher`, which only exempts `_next/static`, `_next/image`, and
 * `favicon.ico`). Purely a temporary visual placeholder, no functionality
 * beyond that.
 */
export function LoginSidePanel() {
  return (
    <div className="relative hidden bg-muted md:block">
      <img
        src="https://ui.shadcn.com/placeholder.svg"
        alt=""
        className="absolute inset-0 h-full w-full object-cover dark:brightness-[0.2] dark:grayscale"
      />
    </div>
  )
}
