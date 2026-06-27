#!/bin/sh
set -e

# Fix storage permissions at runtime (needed because volume mounts
# overwrite the build-time ownership set in the Dockerfile)
chown -R www-data:www-data /opt/dental-lab/moslie-dental-lab/storage
chmod -R 775 /opt/dental-lab/moslie-dental-lab/storage
chown -R www-data:www-data /opt/dental-lab/moslie-dental-lab/bootstrap/cache
chmod -R 775 /opt/dental-lab/moslie-dental-lab/bootstrap/cache

cd /opt/dental-lab/moslie-dental-lab

# Run database migrations on startup. Set RUN_MIGRATIONS=false to skip.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    # Wait for the database to accept connections (it may still be starting).
    echo "Waiting for database..."
    tries=0
    until php artisan migrate:status >/dev/null 2>&1; do
        tries=$((tries + 1))
        if [ "$tries" -ge 30 ]; then
            echo "Database not reachable after 30 attempts; skipping migrations." >&2
            break
        fi
        sleep 2
    done

    if [ "$tries" -lt 30 ]; then
        echo "Running migrations..."
        php artisan migrate --force
    fi
fi

# Execute the CMD passed to docker (supervisord)
exec "$@"
