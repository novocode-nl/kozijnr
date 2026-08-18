#!/bin/sh
set -e

# The vendor/ directory lives in a named volume (see docker-compose.yml) so it
# survives independently from the bind-mounted source and doesn't need to
# exist on the host. Install dependencies on first boot or whenever
# composer.lock changes.
if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --no-progress --prefer-dist
fi

exec "$@"
