import * as React from "react"

export type LoadState<T> =
  | { status: "loading" }
  | { status: "error" }
  | { status: "loaded"; data: T }

/**
 * Shared fetch-on-mount pattern for the list pages: loading -> loaded/error,
 * with the usual cancelled-flag guard so a response landing after unmount
 * (or after the deps changed) never writes stale state. Returns the raw
 * setter so call sites keep their existing optimistic updates (e.g.
 * prepending a freshly created user) verbatim.
 *
 * Deliberately no synchronous reset to "loading" on a deps change (React's
 * set-state-in-effect lint forbids it, and the pre-hook components never
 * had one either): a caller that wants a fresh loading state on a changing
 * input should key-remount, the way the tenant detail page already keys
 * TenantUsersTab by subdomain.
 */
export function useLoadState<T>(
  load: () => Promise<T>,
  deps: React.DependencyList
): [LoadState<T>, React.Dispatch<React.SetStateAction<LoadState<T>>>] {
  const [state, setState] = React.useState<LoadState<T>>({ status: "loading" })

  React.useEffect(() => {
    let cancelled = false

    load()
      .then((data) => {
        if (!cancelled) setState({ status: "loaded", data })
      })
      .catch(() => {
        if (!cancelled) setState({ status: "error" })
      })

    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- caller-supplied deps, same contract as useEffect itself
  }, deps)

  return [state, setState]
}
