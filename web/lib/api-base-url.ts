/**
 * Where the REST API lives, derived from the host the page was served on.
 *
 * The frontend is a pure REST client: every subdomain it is served on
 * (admin.<base>, <tenant>.<base>) talks to the one API host api.<base>.
 * Deriving it from the current host means no env var to keep in sync per
 * worktree (admin.koz-16.kozijnr.localhost -> api.koz-16.kozijnr.localhost)
 * or per environment (admin.kozijnr.nl -> api.kozijnr.nl). The API learns
 * which tenant/admin context the call belongs to from the browser-set
 * Origin header (see the backend's TenantResolverListener).
 */
export function apiBaseUrl(host: string, protocol: string = "http:"): string {
  const [hostname, port] = host.split(":")
  const labels = hostname.split(".")
  labels[0] = "api"

  return `${protocol}//${labels.join(".")}${port ? `:${port}` : ""}`
}
