import { getAppContext } from "@/lib/context/app-context"

/**
 * Deliberate placeholder (KOZ-13, now rendered inside the shared shell from
 * KOZ-14 — see app/(app)/layout.tsx): the real dashboard content for either
 * context is out of this ticket's scope too, only the layout/sidebar shell
 * it now renders inside is.
 *
 * KOZ-14 rework: functional review asked for one path, `/dashboard`, for
 * both contexts — admin.<domein>/dashboard and <tenant>.<domein>/dashboard
 * — rather than a separate `/admin` route. The Host-based context detection
 * (lib/context/app-context.ts) already exists and already drives which
 * sidebar menu renders (app/(app)/layout.tsx); this page reuses the exact
 * same detection to pick which placeholder copy to show, so there is only
 * ever one page component to maintain instead of two near-identical ones
 * that could drift apart.
 */
export default async function DashboardPage() {
  const context = await getAppContext()

  const copy =
    context === "admin"
      ? {
          title: "Admin",
          body: "Dit is een tijdelijke plaatsvervanger voor de echte admin-dashboardinhoud.",
        }
      : {
          title: "Ingelogd",
          body: "Dit is een tijdelijke plaatsvervanger voor de echte tenant-dashboardinhoud.",
        }

  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-2 rounded-xl bg-muted/50 p-6 text-center">
      <h1 className="text-2xl font-bold">{copy.title}</h1>
      <p className="text-muted-foreground">{copy.body}</p>
    </div>
  )
}
