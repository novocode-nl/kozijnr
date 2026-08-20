"use client"

import { useRouter } from "next/navigation"

import { Button } from "@/components/ui/button"

/**
 * Deliberate placeholder (KOZ-13): the real tenant dashboard/sidebar is
 * KOZ-14's scope, explicitly out of scope here. This route exists only so
 * a successful login has somewhere to redirect to.
 *
 * KOZ-13 rework: route protection moved server-side (proxy.ts), since
 * the bearer token now lives in an HttpOnly cookie this component has no
 * way to read — checking for it client-side, the way this page used to,
 * is no longer possible (nor desirable: that was precisely the
 * XSS-exposed approach this rework replaces). By the time this component
 * ever renders, proxy.ts has already confirmed the cookie is present.
 */
export default function DashboardPlaceholderPage() {
  const router = useRouter()

  async function handleLogout() {
    // Best-effort: app/api/logout/route.ts always resolves regardless of
    // the backend call's outcome, so there is nothing further to branch on
    // here — always navigate back to /login afterwards.
    await fetch("/api/logout", { method: "POST" })
    router.push("/login")
  }

  return (
    <div className="flex min-h-svh flex-col items-center justify-center gap-4 p-6 text-center">
      <h1 className="text-2xl font-bold">Ingelogd</h1>
      <p className="text-muted-foreground">
        Dit is een tijdelijke plaatsvervanger voor het echte dashboard (zie
        KOZ-14).
      </p>
      <Button variant="outline" onClick={handleLogout}>
        Uitloggen
      </Button>
    </div>
  )
}
