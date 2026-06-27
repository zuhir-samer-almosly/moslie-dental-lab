#!/bin/bash
# Unattended backup wrapper for scheduled runs.
# Ensures Docker Desktop + the DB container are up, then runs db-backup.sh.
# Logs to backups/auto-backup.log. Safe to run when Docker is already running.
#
# ⚠️  LOCAL DEV ONLY (Windows + WSL + Docker Desktop). Do NOT run this on the
# Linux VPS: it launches "Docker Desktop.exe", targets the *-local containers,
# and pushes to OneDrive/Google Drive. For server backups use the cron-based
# script in DEPLOYMENT.md ("Set Up Automatic Backups") instead.
set -u

HERE="$(cd "$(dirname "$0")" && pwd)"
COMPOSE="$HERE/docker-compose.local.yml"
CONTAINER="moslie-db-local"
LOG="$HERE/backups/auto-backup.log"
DOCKER_DESKTOP="/mnt/c/Program Files/Docker/Docker/Docker Desktop.exe"

mkdir -p "$HERE/backups"
exec >>"$LOG" 2>&1
echo "===== $(date '+%Y-%m-%d %H:%M:%S') auto-backup start ====="

# 1) Make sure the Docker daemon is up (start Docker Desktop if needed).
if ! docker info >/dev/null 2>&1; then
  echo "Docker daemon down — launching Docker Desktop..."
  nohup "$DOCKER_DESKTOP" >/dev/null 2>&1 &
  for i in $(seq 1 60); do
    docker info >/dev/null 2>&1 && break
    sleep 5
  done
fi
if ! docker info >/dev/null 2>&1; then
  echo "❌ Docker daemon did not start within timeout — aborting."
  exit 1
fi

# 2) Make sure the DB container is running.
if ! docker ps --format '{{.Names}}' | grep -qx "$CONTAINER"; then
  echo "Starting containers..."
  docker compose -f "$COMPOSE" up -d
  # Give MySQL a moment to accept connections.
  for i in $(seq 1 24); do
    docker exec "$CONTAINER" sh -c 'mysqladmin -uroot -proot ping' >/dev/null 2>&1 && break
    sleep 5
  done
fi

# 3) Run the actual backup (local + OneDrive + Google Drive).
"$HERE/db-backup.sh"
echo "===== $(date '+%Y-%m-%d %H:%M:%S') auto-backup done (exit $?) ====="
