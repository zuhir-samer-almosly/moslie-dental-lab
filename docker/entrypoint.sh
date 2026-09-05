#!/bin/sh
set -e

APP_DIR=/opt/dental-lab/moslie-dental-lab

# Fix storage permissions at runtime (needed because volume mounts
# overwrite the build-time ownership set in the Dockerfile)
fix_permissions() {
    chown -R www-data:www-data "$APP_DIR/storage"
    chmod -R 775 "$APP_DIR/storage"
    chown -R www-data:www-data "$APP_DIR/bootstrap/cache"
    chmod -R 775 "$APP_DIR/bootstrap/cache"
}

fix_permissions

cd "$APP_DIR"

# Run database migrations on startup. Set RUN_MIGRATIONS=false to skip.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    # Wait for the database to accept connections (it may still be starting).
    echo "Waiting for database..."
    # Up to ~5 minutes: a fresh MySQL volume can take well over a minute to
    # initialize on a small host, and a first deploy on a slow disk has been
    # seen to need more than three.
    max_tries=150
    tries=0
    until php artisan migrate:status >/dev/null 2>&1; do
        tries=$((tries + 1))
        if [ "$tries" -ge "$max_tries" ]; then
            echo "Database not reachable after $max_tries attempts; skipping migrations." >&2
            break
        fi
        sleep 2
    done

    if [ "$tries" -lt "$max_tries" ]; then
        echo "Running migrations..."
        php artisan migrate --force
    fi
fi

# Re-apply permissions: every artisan command above ran as root, so anything
# they created (storage/logs/laravel.log in particular, written by the failing
# migrate:status probes) is root-owned. supervisord runs the queue worker and
# scheduler as www-data, and they crash-loop on a root-owned log file.
fix_permissions

# Execute the CMD passed to docker (supervisord)
exec "$@"
