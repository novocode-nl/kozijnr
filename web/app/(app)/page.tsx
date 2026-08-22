import { getAppContext } from "@/lib/context/app-context"

/**
 * Deliberate placeholder. The real home-page content for either context is
 * out of scope here. This is the single authenticated home for both
 * contexts, living at `/` — `/dashboard` redirects here (see proxy.ts)
 * rather than existing as its own route. Reuses the Host-based context
 * detection (lib/context/app-context.ts) that also drives the sidebar
 * menu (app/(app)/layout.tsx) to pick the placeholder copy, so there is
 * only one page component instead of two near-identical ones.
 */
export default async function HomePage() {
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
