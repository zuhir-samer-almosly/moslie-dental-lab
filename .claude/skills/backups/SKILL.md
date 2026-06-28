---
name: backups
description: Run, verify, or restore the off-site database backup for moslie-dental-lab (spatie/laravel-backup → Google Drive). Use when asked to back up the database, check that backups are running, restore from a backup, recover lost data, or re-mint the Google Drive token.
---

# Backups for moslie-dental-lab

Production takes a **nightly off-site backup of the MySQL database** via
`spatie/laravel-backup`, uploaded to **Google Drive** (disk `google`, via
`masbug/flysystem-google-drive-ext`). Schedule (`routes/console.php`, needs the
`laravel-schedule` supervisor running on the VPS):
- `backup:clean` daily at **01:00** — prunes old backups per `config/backup.php` retention.
- `backup:run` daily at **01:30** — dumps the DB and uploads a `.zip` to Drive.

Each backup is a zip named `<APP_NAME>/<timestamp>.zip` containing a single
`.sql` dump (db only — `config/backup.php` backs up the database, not app files).
Related memory: `backups-google-drive`.

## Verify backups are healthy
```bash
# On the VPS:
docker compose exec app php artisan backup:list
```
This shows each destination disk, the newest backup age, and total size. A healthy
`google` disk shows a backup younger than ~24h. If it's stale or the disk errors,
the OAuth token or scheduler is the usual cause (see Troubleshooting).

## Run a backup on demand
```bash
docker compose exec app php artisan backup:run            # db + (any configured files)
docker compose exec app php artisan backup:run --only-db  # database only
```

## Restore from a backup (manual — there is no restore command)
spatie/laravel-backup **only creates** backups; restoring is manual. Steps:

1. **Get the zip.** Download the newest `<APP_NAME>/<timestamp>.zip` from the backup
   Google Drive account (or `download_file_content` via the Google Drive MCP if it's
   the same account), and copy it onto the VPS.
2. **Unzip** to get the `.sql` dump:
   ```bash
   unzip <timestamp>.zip -d restore && ls restore  # → db-dumps/<database>.sql
   ```
3. **Import into MySQL.** The DB runs in the `moslie-db` container (MySQL 8.0,
   database/user `zoher` by default — confirm against the VPS `.env`):
   ```bash
   docker compose exec -T db mysql -uzoher -p"$DB_PASSWORD" zoher < restore/db-dumps/zoher.sql
   ```
   `-T` is required (no TTY for piped stdin). This **overwrites** existing rows for
   any table in the dump — confirm with the user before running on production.
4. Clear caches afterward: `docker compose exec app php artisan optimize:clear`.

## Troubleshooting
- **No backups / Drive auth errors:** the refresh token expired or was never set.
  Re-mint it locally (needs a browser) with `php artisan backup:google-token`, then
  put the printed value in the VPS `.env` as `GOOGLE_DRIVE_REFRESH_TOKEN` and
  re-deploy. The command's header comment documents the consent flow.
- **`mysqldump` errors about auth plugin:** the `db` service is pinned to
  `--default-authentication-plugin=mysql_native_password` in `docker-compose.yml`
  exactly so the (MariaDB-based) dump client works — don't change it.
- **Backups never fire:** confirm the `laravel-schedule` supervisor program is
  running on the VPS (the scheduler must be alive for `backup:run` to trigger).

## How to suggest it
Production commands run on the VPS, which the user executes themselves — give the
exact one-liner. For a **restore**, always confirm before importing: it overwrites
live data. Deploying schema changes is a separate workflow (the `deploy` skill).
