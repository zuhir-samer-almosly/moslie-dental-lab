#!/bin/bash
# Restore the local MySQL database from a backup .sql file.
# Usage: ./db-restore.sh backups/moslie_YYYYMMDD_HHMMSS.sql
set -e

CONTAINER="moslie-db-local"
DB="zoher"
FILE="$1"

if [ -z "$FILE" ] || [ ! -f "$FILE" ]; then
  echo "Usage: ./db-restore.sh <path-to-backup.sql>"
  echo "Available backups:"
  ls -1t "$(dirname "$0")"/backups/moslie_*.sql 2>/dev/null || echo "  (none found)"
  exit 1
fi

echo "⚠️  This will OVERWRITE the '$DB' database with: $FILE"
read -r -p "Type 'yes' to continue: " confirm
[ "$confirm" = "yes" ] || { echo "Aborted."; exit 1; }

docker exec -i "$CONTAINER" sh -c "mysql -uroot -proot" < "$FILE" 2>/dev/null

echo "✅ Restored '$DB' from $FILE"
