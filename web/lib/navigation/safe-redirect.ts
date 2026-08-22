/**
 * KOZ-20: shared logic for "come back here after login" redirects.
 *
 * proxy.ts's route guard (KOZ-14) redirects an unauthenticated visitor to
 * `/login`, carrying the page they originally asked for as a query param so
 * the login form can send them back there on success. This file is the one
 * place that both (a) builds that query param on the way to `/login`, and
 * (b) validates/sanitizes it on the way back — kept together so the two
 * halves of the contract (what gets written, what gets trusted) can't drift.
 *
 * The query param's value is untrusted input on the read side: proxy.ts
 * only ever writes the current request's own pathname+search into it, but
 * nothing stops a visitor from hand-crafting `/login?redirect=<anything>`
 * themselves. `sanitizeRedirectTarget` is the open-redirect guard — it only
 * ever hands back a same-origin, relative path, never an absolute URL
 * (`https://evil.example`) or a scheme-relative one (`//evil.example`,
 * which browsers resolve as protocol-relative to a different host).
 */

/** Query param name carrying the original destination on the way to /login. */
export const REDIRECT_PARAM = "redirect"

/**
 * Where a plain, un-redirected login sends the user — unchanged default
 * behavior for "navigated to /login directly" (out of scope for KOZ-20) and
 * the fallback whenever the carried value fails validation. `/` (KOZ-21) —
 * the authenticated home within the shared AppShell now that /dashboard no
 * longer exists as its own route.
 */
export const DEFAULT_REDIRECT_PATH = "/"

/**
 * True only for a same-origin, relative path this app can safely redirect
 * to: starts with a single `/`, never `//...` or `/\...` (both of which
 * browsers can resolve as scheme-relative to an attacker-controlled host),
 * and carries no scheme of its own.
 */
export function isSafeRedirectPath(path: string): boolean {
  if (path === "") {
    return false
  }

  if (!path.startsWith("/") || path.startsWith("//") || path.startsWith("/\\")) {
    return false
  }

  // A relative path has no `scheme:` prefix. `new URL` with a base resolves
  // any absolute/scheme-relative value against that base instead of
  // throwing, so compare the resolved origin back to the base's — the
  // reliable way to catch tricks like "/\t/evil.com" or embedded control
  // characters the plain prefix checks above don't cover.
  try {
    const base = "http://redirect-target.invalid"
    const resolved = new URL(path, base)
    return resolved.origin === base
  } catch {
    return false
  }
}

/**
 * Builds the value to store in `REDIRECT_PARAM` from the page the guard is
 * about to bounce the visitor away from (its own pathname + search, e.g.
 * `/dashboard/settings?tab=billing`). Never includes `/login` itself.
 */
export function buildRedirectTarget(pathname: string, search: string): string {
  return `${pathname}${search}`
}

/**
 * Reads a carried redirect target back out and returns a value that is
 * always safe to `router.push` — the sanitized target if valid, otherwise
 * the default post-login destination.
 */
export function sanitizeRedirectTarget(target: string | null | undefined): string {
  if (!target || !isSafeRedirectPath(target)) {
    return DEFAULT_REDIRECT_PATH
  }

  return target
}
