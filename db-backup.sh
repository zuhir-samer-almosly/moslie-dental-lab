#!/bin/bash
# Dump the local MySQL database to ./backups/ as a timestamped .sql file.
# Usage: ./db-backup.sh
set -e

CONTAINER="moslie-db-local"
DB="zoher"
DIR="$(cd "$(dirname "$0")" && pwd)/backups"
STAMP="$(date +%Y%m%d_%H%M%S)"
OUT="$DIR/moslie_${STAMP}.sql"

mkdir -p "$DIR"

# Verify the database container is running before attempting a dump.
if ! docker ps --format '{{.Names}}' 2>/dev/null | grep -qx "$CONTAINER"; then
  echo "❌ Database container '$CONTAINER' is not running (is Docker Desktop started?)."
  echo "   Start it with: docker compose -f docker-compose.local.yml up -d"
  exit 1
fi

# Dump to a temp file first; only keep it if the dump succeeds and is non-empty,
# so a failed dump never leaves a 0-byte file that could rotate out good backups.
TMP="$(mktemp "${OUT}.tmp.XXXX")"
trap 'rm -f "$TMP"' EXIT

if ! docker exec "$CONTAINER" sh -c \
  "mysqldump -uroot -proot --databases $DB --single-transaction --routines --triggers" \
  > "$TMP" 2>/tmp/dump_err; then
  echo "❌ mysqldump failed: $(tail -1 /tmp/dump_err)"
  exit 1
fi

# Sanity check: a valid dump ends with a completion marker.
if [ ! -s "$TMP" ] || ! grep -q "Dump completed" "$TMP"; then
  echo "❌ Dump looks incomplete/empty — not saving."
  exit 1
fi

mv "$TMP" "$OUT"
trap - EXIT
echo "✅ Backup saved: $OUT ($(du -h "$OUT" | cut -f1))"

# Keep only the 30 most recent local backups
ls -1t "$DIR"/moslie_*.sql 2>/dev/null | tail -n +31 | xargs -r rm --

# --- Copy to cloud destinations (off-machine) ----------------------------
# Helper: copy the backup into a folder and trim to the 60 most recent.
copy_to() {
  local label="$1" dest="$2"
  mkdir -p "$dest" || return 1
  cp "$OUT" "$dest/" || return 1
  echo "☁️  Copied to $label: $dest/$(basename "$OUT")"
  ls -1t "$dest"/moslie_*.sql 2>/dev/null | tail -n +61 | xargs -r rm --
}

copied=0

# OneDrive — already synced on this machine. Override with ONEDRIVE_BACKUP_DIR.
ONEDRIVE_DEST="${ONEDRIVE_BACKUP_DIR:-/mnt/c/Users/zoher/OneDrive/moslie-backups}"
if [ -d "$(dirname "$ONEDRIVE_DEST")" ]; then
  copy_to "OneDrive" "$ONEDRIVE_DEST" && copied=1
fi

# Google Drive — via rclone (no desktop app needed).
#   Remote name:   RCLONE_REMOTE   (default: gdrive)
#   Remote folder: RCLONE_PATH     (default: moslie-backups)
RCLONE_REMOTE="${RCLONE_REMOTE:-gdrive}"
RCLONE_PATH="${RCLONE_PATH:-moslie-backups}"
if command -v rclone >/dev/null 2>&1 && rclone listremotes 2>/dev/null | grep -q "^${RCLONE_REMOTE}:"; then
  if rclone copy "$OUT" "${RCLONE_REMOTE}:${RCLONE_PATH}" 2>/tmp/rclone_err; then
    echo "☁️  Uploaded to Google Drive (rclone): ${RCLONE_REMOTE}:${RCLONE_PATH}/$(basename "$OUT")"
    copied=1
    # Keep only the 60 most recent on the remote (timestamped names sort chronologically)
    rclone lsf "${RCLONE_REMOTE}:${RCLONE_PATH}" --include "moslie_*.sql" 2>/dev/null \
      | sort -r | tail -n +61 \
      | while read -r old; do rclone deletefile "${RCLONE_REMOTE}:${RCLONE_PATH}/$old" 2>/dev/null; done
  else
    echo "⚠️  rclone upload failed: $(cat /tmp/rclone_err | tail -1)"
    echo "    Token may be expired — run: rclone config reconnect ${RCLONE_REMOTE}:"
  fi
else
  echo "ℹ️  rclone remote '${RCLONE_REMOTE}:' not available — skipping Google Drive."
fi

if [ "$copied" -eq 0 ]; then
  echo "⚠️  No cloud destination available — backup kept locally only."
fi

exit 0
