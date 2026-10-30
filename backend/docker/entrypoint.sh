#!/bin/sh
set -e

# The api and reverb containers share this script. Only the api container
# prepares the app (install, key, migrate) so the two don't race each other.
if [ "$CONTAINER_ROLE" = "api" ]; then
    if [ ! -f .env ]; then
        cp .env.example .env
    fi

    composer install --no-interaction --prefer-dist

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
