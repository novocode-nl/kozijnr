/**
 * Name of the HttpOnly cookie the backend sets on a successful login
 * (KOZ-13 rework — see
 * api/src/TenantUser/Infrastructure/Security/TenantApiTokenCookie.php,
 * the source of truth this name must keep matching).
 *
 * The token itself is never read or stored anywhere in this frontend
 * codebase anymore — HttpOnly means client-side JS can't read the cookie's
 * value even if it tried, and the server-side proxy routes
 * (app/api/login/route.ts, app/api/logout/route.ts) never need to inspect
 * it either, only forward it verbatim between the browser and the
 * backend. The one place this name is needed is proxy.ts, to check
 * whether the cookie is merely *present* before allowing a request through
 * to the authenticated area.
 */
export const TENANT_TOKEN_COOKIE_NAME = "tenant_api_token"
