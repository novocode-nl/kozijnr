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
LOCKFILE_HASH_FILE="node_modules/.package-lock.json.sha256"

current_hash="$(sha256sum package-lock.json | cut -d ' ' -f 1)"
installed_hash="$(cat "$LOCKFILE_HASH_FILE" 2>/dev/null || true)"

if [ ! -x node_modules/.bin/next ] || [ "$current_hash" != "$installed_hash" ]; then
    npm install
    echo "$current_hash" > "$LOCKFILE_HASH_FILE"
fi

exec "$@"
