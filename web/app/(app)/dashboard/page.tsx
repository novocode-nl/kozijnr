import { getAppContext } from "@/lib/context/app-context"

/**
 * Deliberate placeholder. One `/dashboard` page for both admin and tenant
 * contexts, reusing the Host-based detection to pick the copy, so there's
 * no separate near-identical page to drift out of sync.
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
