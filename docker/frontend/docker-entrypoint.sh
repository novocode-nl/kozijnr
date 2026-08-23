#!/bin/sh
set -e

# node_modules lives in a named volume (see docker-compose.yml) so it's built
# for the container's architecture/libc instead of the host's, and doesn't
# need to exist on the host at all. Install on first boot or whenever the
# lockfile changes.
#
# We can't rely on node_modules/.bin/next existing as a signal that the
# install is up to date: the volume persists across rebuilds, so an install
# that ran before package-lock.json changed leaves a stale node_modules with
# next still present but missing/mismatched newer dependencies. Instead,
# hash package-lock.json and compare against the hash recorded by the last
# successful install; only skip installing when both the binary exists and
# the lockfile hasn't changed since.
#
# node_modules_is_healthy also guards against a second, independent failure
# mode (KOZ-28): on Docker Desktop/macOS, the frontend_node_modules named
# volume has been observed going empty *while the container keeps running*
# (no restart, no rebuild) — most likely a VirtioFS sync race with a large
# node_modules tree. A single `-x node_modules/.bin/next` check is not
# reliable evidence either way in that situation: a dangling symlink or a
# not-yet-synced stat can make it look present or absent inconsistently. So
# health is judged on two independent, cheap signals instead of one: the
# entry package.json actually has content, and the tree as a whole still has
# roughly the number of top-level packages a real install produces (a fresh
# install currently adds 600+; 50 is a floor well below that, chosen so a
# lockfile change that merely adds/removes a handful of packages never
# false-positives as "corrupt").
LOCKFILE_HASH_FILE="node_modules/.package-lock.json.sha256"

node_modules_is_healthy() {
    [ -s node_modules/next/package.json ] \
        && [ "$(find node_modules -mindepth 1 -maxdepth 1 -type d 2>/dev/null | wc -l)" -ge 50 ]
}

current_hash="$(sha256sum package-lock.json | cut -d ' ' -f 1)"
installed_hash="$(cat "$LOCKFILE_HASH_FILE" 2>/dev/null || true)"

if ! node_modules_is_healthy || [ "$current_hash" != "$installed_hash" ]; then
    npm install
    echo "$current_hash" > "$LOCKFILE_HASH_FILE"
fi

# Runtime self-heal for the same KOZ-28 failure mode: the check above only
# runs once, at container start, so it can't catch node_modules going empty
# hours into a long-running dev session — which is exactly when it was
# observed, mid user-review, with no restart in between. Poll node_modules'
# health periodically in the background while the dev server runs; if it's
# found empty/corrupt, stop the dev server so the container exits. Combined
# with `restart: unless-stopped` on the frontend service (docker-compose.yml),
# Docker restarts the container, which re-runs this script's install check
# above and reinstalls into the now-empty volume — no human needing to
# notice an Internal Server Error and manually restart first.
(
    while sleep 60; do
        if ! node_modules_is_healthy; then
            echo "docker-entrypoint: node_modules looks empty/corrupt while running — stopping so the container restarts and reinstalls." >&2
            kill -TERM 1
            break
        fi
    done
) &

exec "$@"
