import * as React from "react"

const MOBILE_BREAKPOINT = 768

function subscribe(callback: () => void) {
  const mql = window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT - 1}px)`)
  mql.addEventListener("change", callback)
  return () => mql.removeEventListener("change", callback)
}

function getSnapshot() {
  return window.innerWidth < MOBILE_BREAKPOINT
}

// SSR renders the desktop layout unconditionally (no `window` to check),
// same value `useSyncExternalStore` reconciles against during hydration.
function getServerSnapshot() {
  return false
}

/**
 * `useSyncExternalStore` rather than `useState` + `useEffect`: this hook
 * mirrors an external, mutable source (`window.matchMedia`), which is
 * exactly the case `useSyncExternalStore` exists for — it renders
 * `getServerSnapshot` on the server *and* on the client's first pass (so
 * hydration always matches, even on a genuinely mobile viewport where the
 * real value differs), then re-renders with the real `getSnapshot` value
 * right after. A `useState`/`useEffect` version that setState's inside the
 * effect body either mismatches SSR (computing the real value eagerly) or
 * trips `react-hooks/set-state-in-effect` (setting it after the fact) —
 * this sidesteps both instead of picking one of those trade-offs.
 */
export function useIsMobile() {
  return React.useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot)
}
