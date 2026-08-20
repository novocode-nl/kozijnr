/**
 * Placeholder (KOZ-14): exists so the admin menu variant of the shared
 * shell (see app/(app)/layout.tsx) has somewhere to render, exactly like
 * app/(app)/dashboard/page.tsx does for the tenant variant. Real admin
 * dashboard content, and a real admin-frontend login/session guard for
 * this route, are both out of scope here — see
 * lib/context/app-context.ts for why this route isn't guarded yet.
 */
export default function AdminPlaceholderPage() {
  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-2 rounded-xl bg-muted/50 p-6 text-center">
      <h1 className="text-2xl font-bold">Admin</h1>
      <p className="text-muted-foreground">
        Dit is een tijdelijke plaatsvervanger voor de echte admin-dashboardinhoud.
      </p>
    </div>
  )
}
