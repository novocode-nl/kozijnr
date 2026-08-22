/**
 * Name of the HttpOnly cookie the backend sets on a successful login — must
 * match `TenantApiTokenCookie.php` on the backend. The token value itself
 * is never read here (HttpOnly); proxy.ts only checks it's present.
 */
export const TENANT_TOKEN_COOKIE_NAME = "tenant_api_token"
