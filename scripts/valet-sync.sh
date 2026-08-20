#!/usr/bin/env bash
# Drain this worktree's pending Valet proxy-request queue (KOZ-12, rework),
# registering every queued <tenant>.<base>.test proxy with the real host
# `valet` CLI.
#
# Usage:
#   scripts/valet-sync.sh
#
# (No issue-number argument, unlike setup-worktree-env.sh / teardown-worktree-valet.sh:
# every queued request already carries its own fully-qualified domain and
# target, so this script only ever needs to know which worktree it's
# running in — the one it's invoked from.)
#
# Why this exists (KOZ-12 rework): the backend runs inside a Docker
# container that has no `valet` binary and no access to the host's Valet
# installation at all, so App\Tenancy\Infrastructure\Valet\TenantValetProxyListener
# can never call `valet proxy` itself, no matter the error handling. Instead
# it queues each request as a small JSON file (via
# App\Tenancy\Infrastructure\Valet\FilesystemValetProxyQueue) under
# api/var/valet-proxy-queue/pending/ — a path that's already visible on the
# host thanks to docker-compose.yml's `./api:/app` bind mount, no extra
# Docker volume required. This script is the other half: run on the host,
# it actually calls `valet proxy` for each pending request.
#
# A continuously-running host-side daemon/listener (reachable from the
# container over host.docker.internal) was considered and deliberately
# rejected in favour of this: it would need to be started, kept running and
# managed by hand (e.g. as a launchd service) for something that's only
# needed right after creating a tenant. A file-based queue plus an
# on-demand `make valet-sync` needs no background process, no open port,
# and no separate lifecycle to manage — just filesystem I/O, run whenever
# it's convenient. See README.md "Local domains via Laravel Valet" for the
# full reasoning, including an optional `fswatch`-based one-liner for
# near-realtime sync without any of this becoming a hard requirement.
#
# Processed requests are moved to api/var/valet-proxy-queue/processed/ (kept
# for troubleshooting, never re-registered); a request that fails to
# register (e.g. Valet not installed, `valet proxy` erroring) is left in
# place so the next `make valet-sync` retries it.

set -euo pipefail

if [ "$#" -ne 0 ]; then
  echo "Usage: $0" >&2
  echo "(no arguments — this always operates on the worktree it's run from)" >&2
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

QUEUE_DIR="$REPO_ROOT/api/var/valet-proxy-queue"
PENDING_DIR="$QUEUE_DIR/pending"
PROCESSED_DIR="$QUEUE_DIR/processed"

if ! command -v valet >/dev/null 2>&1; then
  echo "Note: 'valet' CLI not found — nothing can be synced, skipping." >&2
  echo "  See README.md \"Local domains via Laravel Valet\" to install Valet." >&2
  exit 0
fi

if [ ! -d "$PENDING_DIR" ]; then
  echo "Nothing to sync — no pending proxy requests (${PENDING_DIR} does not exist yet)."
  exit 0
fi

shopt -s nullglob
PENDING_FILES=("$PENDING_DIR"/*.json)
shopt -u nullglob

if [ "${#PENDING_FILES[@]}" -eq 0 ]; then
  echo "Nothing to sync — no pending proxy requests."
  exit 0
fi

mkdir -p "$PROCESSED_DIR"

# Extracts a single string field from one of our own queue files. We control
# the writer (App\Tenancy\Infrastructure\Valet\FilesystemValetProxyQueue),
# so the JSON is always a flat {"domain": "...", "target": "..."} object —
# `jq` is used when available (more robust), with a plain grep/sed fallback
# for machines that have `valet`/PHP but not `jq`.
extract_field() {
  local file="$1" field="$2"

  if command -v jq >/dev/null 2>&1; then
    jq -r --arg f "$field" '.[$f]' "$file"
  else
    grep -o "\"${field}\"[[:space:]]*:[[:space:]]*\"[^\"]*\"" "$file" \
      | sed -E 's/.*:[[:space:]]*"([^"]*)"$/\1/'
  fi
}

SYNCED=0
FAILED=0

for file in "${PENDING_FILES[@]}"; do
  domain="$(extract_field "$file" domain)"
  target="$(extract_field "$file" target)"

  if [ -z "$domain" ] || [ -z "$target" ]; then
    echo "  Skipping malformed queue file: ${file}" >&2
    continue
  fi

  if valet proxy "$domain" "$target"; then
    mv "$file" "$PROCESSED_DIR/$(basename "$file")"
    echo "  Synced: ${domain} -> ${target}"
    SYNCED=$((SYNCED + 1))
  else
    echo "  Warning: 'valet proxy ${domain} ${target}' failed — left pending for retry." >&2
    FAILED=$((FAILED + 1))
  fi
done

echo ""
echo "Done: ${SYNCED} proxy request(s) synced, ${FAILED} failed (left pending for retry)."
