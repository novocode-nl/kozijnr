#!/bin/sh
set -e

# node_modules lives in a named volume (see docker-compose.yml) so it's built
# for the container's architecture/libc instead of the host's, and doesn't
# need to exist on the host at all. Install on first boot or whenever the
# lockfile changes.
if [ ! -x node_modules/.bin/next ]; then
    npm install
fi

exec "$@"
