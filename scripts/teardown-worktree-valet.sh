#!/usr/bin/env bash
# Remove a worktree's Laravel Valet proxies (KOZ-12), so cleaning up a
# worktree doesn't leave dead proxy registrations pointing at a port that no
# longer has any container listening on it.
#
# Usage:
#   scripts/teardown-worktree-valet.sh <issue-number>
#
# Example:
#   scripts/teardown-worktree-valet.sh 12
#
# Removes:
#   - The two static proxies scripts/setup-worktree-env.sh registers:
#     api.kozijnr-koz-<n>.test, admin.kozijnr-koz-<n>.test
#   - Every dynamic per-tenant proxy under that worktree's domain
#     (<tenant>.kozijnr-koz-<n>.test), by listing Valet's registered proxies
#     and unproxying every one that matches the worktree's domain suffix —
#     there's no fixed list of tenant names to remove by name, since tenants
#     are created ad hoc per worktree (App\Tenancy\Infrastructure\Valet\TenantValetProxyListener).
#
# Called from the asana-user-review skill's worktree-cleanup step, right
# alongside `git worktree remove` / `git branch -d`, after a ticket's PR has
# been merged. Safe to run even if some/all of these proxies were never
# registered (e.g. Valet wasn't installed on this machine, or no tenant was
# ever created in this worktree) — every removal is best-effort and never
# fails the script.
#
# No-ops entirely (with a note) if the `valet` CLI isn't available.

set -euo pipefail

if [ "$#" -ne 1 ]; then
  echo "Usage: $0 <issue-number>" >&2
  echo "Example: $0 12   # removes Valet proxies for KOZ-12's worktree" >&2
  exit 1
fi

N="$1"

if ! [[ "$N" =~ ^[1-9][0-9]*$ ]]; then
  echo "Error: issue number must be a positive integer without leading zeros, got '$N'" >&2
  exit 1
fi

VALET_DOMAIN="kozijnr-koz-${N}.test"

if ! command -v valet >/dev/null 2>&1; then
  echo "Note: 'valet' CLI not found — nothing to remove, skipping."
  exit 0
fi

echo "Removing Valet proxies for ${VALET_DOMAIN}..."

# Static proxies (api./admin.) — always attempt, regardless of whether they
# were actually registered.
for prefix in api admin; do
  domain="${prefix}.${VALET_DOMAIN}"
  if valet unproxy "$domain" >/dev/null 2>&1; then
    echo "  Removed proxy: ${domain}"
  else
    echo "  No proxy to remove for: ${domain} (already absent, or Valet reported no such site)"
  fi
done

# Dynamic per-tenant proxies: list every registered proxy and unproxy the
# ones under this worktree's domain. `valet proxies` output format has
# varied across Valet versions (a table, or JSON with --json) — this parses
# the first whitespace-separated column as the domain, which matches the
# plain-table output; use `valet proxies --json` and adjust this parsing if
# your Valet version's plain output differs.
if command -v valet >/dev/null 2>&1 && valet proxies >/tmp/koz-valet-proxies-$$.txt 2>/dev/null; then
  while IFS= read -r line; do
    domain=$(echo "$line" | awk '{print $1}')
    case "$domain" in
      *".${VALET_DOMAIN}")
        if valet unproxy "$domain" >/dev/null 2>&1; then
          echo "  Removed tenant proxy: ${domain}"
        fi
        ;;
    esac
  done < /tmp/koz-valet-proxies-$$.txt
  rm -f /tmp/koz-valet-proxies-$$.txt
fi

echo "Done removing Valet proxies for ${VALET_DOMAIN}."
