/**
 * Where the REST API lives, derived from the host the page was served on:
 * every subdomain (admin.<base>, <tenant>.<base>) talks to api.<base>.
 * Deriving it from the current host avoids an env var to keep in sync per
 * environment/worktree.
 */
export function apiBaseUrl(host: string, protocol: string = "http:"): string {
  const [hostname, port] = host.split(":")
  const labels = hostname.split(".")
  labels[0] = "api"

  return `${protocol}//${labels.join(".")}${port ? `:${port}` : ""}`
}
