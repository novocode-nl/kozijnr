import type { LoginFormValues } from "@/lib/schemas/login"

/**
 * Generic message the backend always returns for /api/login, regardless of
 * what specifically made the credentials invalid (KOZ-11 DoD: no
 * distinguishing technical detail leaks to the client). Used as the
 * fallback whenever the response can't be parsed at all, so the UI never
 * shows anything more specific than the backend itself would.
 */
export const GENERIC_LOGIN_ERROR = "Invalid credentials."

/**
 * Calls this app's own POST /api/login route handler (app/api/login/route.ts),
 * which proxies to the actual backend (KOZ-11) server-side — see that
 * route's docstring for why a same-origin proxy is used instead of calling
 * the backend directly from the browser (tenant subdomain + CORS).
 *
 * KOZ-13 rework: on success there is no token to hand back to the caller
 * anymore — the backend sets it as an HttpOnly cookie
 * (App\TenantUser\Infrastructure\Security\TenantApiTokenCookie), forwarded
 * onto the browser's response by the proxy route itself. This function's
 * only job is to report whether the login succeeded.
 *
 * Never throws for an invalid-credentials response — that is a normal,
 * expected outcome the caller renders inline — only for genuine
 * network/unexpected failures, always with the same generic message so
 * nothing technical ever reaches the UI.
 */
export async function login(
  values: LoginFormValues
): Promise<{ success: true } | { success: false; message: string }> {
  return postCredentials("/api/login", values)
}

/**
 * Calls this app's own POST /api/admin/login route handler
 * (app/api/admin/login/route.ts), which proxies to the backend's
 * super-admin login (KOZ-8) server-side — the admin-side counterpart to
 * `login` above. Same reasoning throughout: a same-origin proxy (Host
 * header + no CORS), a generic error message regardless of cause, and no
 * token/session value ever handled here — the backend hands the session
 * back as a `Set-Cookie` the browser stores, forwarded verbatim by the
 * proxy route.
 *
 * KOZ-14 rework (round 5): added alongside components/admin-login-form.tsx
 * so /login has a real, working form for the admin context too, instead of
 * the earlier admin/login placeholder page.
 */
export async function adminLogin(
  values: LoginFormValues
): Promise<{ success: true } | { success: false; message: string }> {
  return postCredentials("/api/admin/login", values)
}

async function postCredentials(
  path: string,
  values: LoginFormValues
): Promise<{ success: true } | { success: false; message: string }> {
  let response: Response
  try {
    response = await fetch(path, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(values),
    })
  } catch {
    return { success: false, message: GENERIC_LOGIN_ERROR }
  }

  if (!response.ok) {
    return { success: false, message: GENERIC_LOGIN_ERROR }
  }

  return { success: true }
}
