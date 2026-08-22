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

// SSR has no `window`, so it renders the desktop layout unconditionally —
// this must match, so hydration doesn't mismatch on a mobile viewport.
function getServerSnapshot() {
  return false
}

/**
 * `useSyncExternalStore` rather than `useState`/`useEffect`: mirrors an
 * external mutable source (`window.matchMedia`) without an SSR/hydration
 * mismatch or tripping `react-hooks/set-state-in-effect`.
 */
export function useIsMobile() {
  return React.useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot)
}
