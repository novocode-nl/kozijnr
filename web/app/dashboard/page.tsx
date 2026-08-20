"use client"

import { useEffect, useState } from "react"
import { useRouter } from "next/navigation"

import { clearStoredToken, getStoredToken } from "@/lib/auth/token"
import { Button } from "@/components/ui/button"

/**
 * Deliberate placeholder (KOZ-13): the real tenant dashboard/sidebar is
 * KOZ-14's scope, explicitly out of scope here. This route exists only so
 * a successful login has somewhere to redirect to, and to prove the stored
 * bearer token round-trips — it is not the dashboard itself.
 */
export default function DashboardPlaceholderPage() {
  const router = useRouter()
  const [token] = useState<string | null>(() => getStoredToken())

  useEffect(() => {
    if (!token) {
      router.replace("/login")
    }
  }, [token, router])

  if (!token) {
    return null
  }

  return (
    <div className="flex min-h-svh flex-col items-center justify-center gap-4 p-6 text-center">
      <h1 className="text-2xl font-bold">Ingelogd</h1>
      <p className="text-muted-foreground">
        Dit is een tijdelijke plaatsvervanger voor het echte dashboard (zie
        KOZ-14).
      </p>
      <Button
        variant="outline"
        onClick={() => {
          clearStoredToken()
          router.push("/login")
        }}
      >
        Uitloggen
      </Button>
    </div>
  )
}
