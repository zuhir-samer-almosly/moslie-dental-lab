#!/bin/sh
set -e

# Fix storage permissions at runtime (needed because volume mounts
# overwrite the build-time ownership set in the Dockerfile)
chown -R www-data:www-data /opt/dental-lab/moslie-dental-lab/storage
chmod -R 775 /opt/dental-lab/moslie-dental-lab/storage
chown -R www-data:www-data /opt/dental-lab/moslie-dental-lab/bootstrap/cache
chmod -R 775 /opt/dental-lab/moslie-dental-lab/bootstrap/cache

# Execute the CMD passed to docker (supervisord)
exec "$@"
