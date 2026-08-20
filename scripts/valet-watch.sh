#!/usr/bin/env bash
# Watch this worktree's pending Valet proxy-request queue and run
# `make valet-sync` automatically whenever a new request shows up (KOZ-12,
# rework round 3), instead of requiring `make valet-sync` to be run by hand
# after every tenant creation.
#
# Usage:
#   scripts/valet-watch.sh
#
# (No issue-number argument, unlike setup-worktree-env.sh /
# teardown-worktree-valet.sh — like valet-sync.sh, this only ever needs to
# know which worktree it's running in.)
#
# Why this exists (KOZ-12, rework round 3): functional review flagged that
# the proxy sync had to be triggered by hand (`make valet-sync`), and asked
# for it to happen automatically after tenant creation — for *both* the
# CLI route (`tenant:provision`) and the admin API route (`POST
# /api/admin/tenants`), since the latter becomes the primary route once an
# admin UI exists (KOZ-13/14).
#
# This script deliberately does NOT distinguish between those two routes at
# all: App\Tenancy\Infrastructure\Valet\TenantValetProxyListener is wired as
# a Doctrine postPersist listener on the Tenant entity, so it fires for
# *any* code path that persists a Tenant, CLI or API alike, and always
# writes to the same api/var/valet-proxy-queue/pending/ directory via
# App\Tenancy\Infrastructure\Valet\FilesystemValetProxyQueue. Watching that
# one directory on the filesystem therefore already covers both routes with
# no extra branching needed here.
#
# This intentionally stays an on-demand, foreground watcher, not a
# background daemon: same reasoning as scripts/valet-sync.sh and
# README.md "Local domains via Laravel Valet" — a host-side daemon needing
# to be started, kept running and managed by hand (e.g. as a launchd
# service) was deliberately rejected earlier in this ticket, and this
# script doesn't reopen that decision. Run it in a spare terminal tab while
# working on tenants; stop it with Ctrl+C when done. The file queue plus
# `make valet-sync` remains the underlying guarantee even if this isn't
# running — this script is purely a convenience on top of it.

set -euo pipefail

if [ "$#" -ne 0 ]; then
  echo "Usage: $0" >&2
  echo "(no arguments — this always operates on the worktree it's run from)" >&2
  exit 1
fi

if ! command -v fswatch >/dev/null 2>&1; then
  echo "Error: 'fswatch' is not installed." >&2
  echo "  Install it with: brew install fswatch" >&2
  echo "  (Or run 'make valet-sync' by hand after creating a tenant instead" >&2
  echo "  of using this watcher.)" >&2
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

QUEUE_DIR="$REPO_ROOT/api/var/valet-proxy-queue"
PENDING_DIR="$QUEUE_DIR/pending"

mkdir -p "$PENDING_DIR"

echo "Watching ${PENDING_DIR} for new Valet proxy requests..."
echo "Running 'make valet-sync' on every change. Press Ctrl+C to stop."
echo ""

# -o makes fswatch emit a single event count per batch of changes instead of
# one line per changed file, which is all `xargs -n1` needs to trigger one
# `make valet-sync` run per batch (it doesn't matter how many files changed
# in one batch — valet-sync.sh always drains everything pending anyway).
exec fswatch -o "$PENDING_DIR" | xargs -n1 -I{} make -C "$REPO_ROOT" valet-sync
