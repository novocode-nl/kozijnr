/**
 * Deliberate placeholder (KOZ-13, now rendered inside the shared shell from
 * KOZ-14 — see app/(app)/layout.tsx): the real tenant dashboard content is
 * out of this ticket's scope too, only the layout/sidebar shell it now
 * renders inside is. This route exists so a successful tenant login has
 * somewhere to redirect to.
 *
 * KOZ-14 rework: logout now lives in the shared shell's sidebar (see
 * components/nav-user.tsx) rather than as a button on this page — this
 * page no longer needs to be a Client Component for that.
 */
export default function DashboardPlaceholderPage() {
  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-2 rounded-xl bg-muted/50 p-6 text-center">
      <h1 className="text-2xl font-bold">Ingelogd</h1>
      <p className="text-muted-foreground">
        Dit is een tijdelijke plaatsvervanger voor de echte tenant-dashboardinhoud.
      </p>
    </div>
  )
}
