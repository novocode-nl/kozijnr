/**
 * Decorative image side panel, shared by the tenant and admin login forms.
 * Points at shadcn's hosted placeholder rather than a local public/ asset,
 * because proxy.ts's auth guard would redirect unauthenticated requests for
 * static assets under /login back to /login itself.
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
