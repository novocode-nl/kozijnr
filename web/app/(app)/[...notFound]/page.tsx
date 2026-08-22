import { notFound } from "next/navigation"

/**
 * Catch-all for any authenticated-area path that doesn't match a real
 * route (KOZ-21). Without this, Next.js has no matched segment for a
 * genuinely unknown path and falls back to a root-level 404 outside any
 * layout; having an actual matched route here — that immediately calls
 * notFound() — makes Next resolve to the nearest not-found.tsx up this same
 * (app) route group (app/(app)/not-found.tsx), so the branded 404 renders
 * inside the shared AppShell instead of the generic Next.js one.
 *
 * Only ever reached by a visitor with a valid session: proxy.ts redirects
 * anyone without one to /login before Next's routing runs at all.
 */
export default function CatchAll() {
  notFound()
}
