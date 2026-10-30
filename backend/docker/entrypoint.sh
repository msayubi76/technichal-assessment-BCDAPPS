#!/bin/sh
set -e

# The api and reverb containers share this script. Only the api container
# prepares the app (install, key, migrate) so the two don't race each other.
if [ "$CONTAINER_ROLE" = "api" ]; then
    if [ ! -f .env ]; then
        cp .env.example .env
    fi

    # Try a few times: on a slow connection some downloads time out, and the
    # next attempt only fetches what is still missing (the rest is cached).
    attempt=1
    until composer install --no-interaction --prefer-dist --no-progress; do
        if [ "$attempt" -ge 3 ]; then
            echo "composer install failed after $attempt attempts" >&2
            exit 1
        fi
        attempt=$((attempt + 1))
        echo "composer install failed, retrying (attempt $attempt of 3)..."
        sleep 5
    done

    if ! grep -q '^APP_KEY=base64' .env; then
        php artisan key:generate --force
    fi

    php artisan config:clear
    php artisan migrate --force

    # Demo data is safe to load more than once, the seeders update rows in place.
    if [ "$AUTO_SEED" = "true" ]; then
        php artisan db:seed --force
    fi
else
    # Wait until the api container has finished installing packages.
    until [ -f vendor/autoload.php ] && [ -f .env ]; do
        echo "Waiting for dependencies..."
        sleep 3
    done
fi

exec "$@"
