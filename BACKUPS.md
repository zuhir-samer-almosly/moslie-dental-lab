# Backups

All real data lives in the **MySQL `db-data` volume on the VPS**. If the server
is lost, not renewed, or rebuilt, that volume goes with it. To protect against
that, the app takes a **nightly off-site backup to Google Drive** (database +
`storage/app`), keeps a rolling history, and can be restored onto a fresh server.

- Powered by [`spatie/laravel-backup`](https://spatie.be/docs/laravel-backup).
- Schedule: defined in `routes/console.php` (`backup:clean` 01:00, `backup:run`
  01:30 daily). Runs via the `laravel-schedule` supervisor program in the app
  container.
- Retention: see `config/backup.php` (`cleanup.default_strategy`).
- Destination disk: `google` (see `config/filesystems.php`).

## One-time setup

### 1. Create Google OAuth credentials (the backup account)

Do this signed in as the **Google account that owns the backup storage** (your
paid 5 TB account):

1. Go to <https://console.cloud.google.com/> → create a project (e.g. "Dental Lab Backups").
2. **APIs & Services → Library →** enable **Google Drive API**.
3. **APIs & Services → OAuth consent screen →** External, fill the app name +
   your email, and add yourself as a **Test user** (this avoids the app-verification
   requirement — test users can authorize indefinitely).
4. **APIs & Services → Credentials → Create credentials → OAuth client ID →**
   Application type **Desktop app**. Copy the **Client ID** and **Client secret**.

### 2. Get a refresh token

Run **locally** (you need a browser). Put the client id/secret in your local
`.env` first, or the command will prompt for them:

```bash
docker exec -it moslie-dental-lab-local php artisan backup:google-token
```

Follow the prompts: open the printed URL, sign in with the **backup** Google
account, approve, then copy the `code=...` value from the (non-loading)
`localhost` URL it redirects to and paste it back. It prints
`GOOGLE_DRIVE_REFRESH_TOKEN=...`.

### 3. Fill in the production `.env`

On the VPS, set these in the project `.env` (see `.env.docker` for the keys):

```
BACKUP_DISK=google
BACKUP_NOTIFICATION_EMAIL=zohermoslie1@gmail.com
GOOGLE_DRIVE_CLIENT_ID=...
GOOGLE_DRIVE_CLIENT_SECRET=...
GOOGLE_DRIVE_REFRESH_TOKEN=...
GOOGLE_DRIVE_FOLDER_ID=          # optional: a Drive folder ID to store backups in
```

`GOOGLE_DRIVE_FOLDER_ID` is the part after `folders/` in a Drive folder URL.
Leave it blank to store at the Drive root.

### 4. Convert the existing DB user to `mysql_native_password`

The dump tool in the app image (MariaDB client) can't authenticate with MySQL 8's
default `caching_sha2_password`. New databases already use `mysql_native_password`
(set via the `command:` flag on the `db` service). The **existing** production
database user must be converted once:

```bash
docker compose exec db mysql -uroot -p"$DB_ROOT_PASSWORD" \
  -e "ALTER USER '$DB_USERNAME'@'%' IDENTIFIED WITH mysql_native_password BY '$DB_PASSWORD'; FLUSH PRIVILEGES;"
```

(Use the values from your `.env`.) Skip this step on a brand-new deployment.

### 5. Deploy and verify

Rebuild on the VPS (see the `deploy` skill), then:

```bash
docker compose exec app php artisan backup:run    # manual backup, now + on demand
docker compose exec app php artisan backup:list    # should show 1 healthy backup on the google disk
```

Check the backup appears in Google Drive.

## Day-to-day

- **Manual backup any time:** `docker compose exec app php artisan backup:run`
- **List backups / health:** `docker compose exec app php artisan backup:list`
- **Daily automatic:** handled by the scheduler; nothing to do.

## Failure alerts

`config/backup.php` emails `BACKUP_NOTIFICATION_EMAIL` on backup/cleanup failure
and when no recent healthy backup is found. These only send if mail is
configured — production currently uses `MAIL_MAILER=log`, so alerts go to the
container logs. To get real emails, set up SMTP in `.env` (e.g. a Gmail app
password or a transactional provider).

## Restoring onto a fresh server

1. Bring up the stack (`docker compose up -d`) so an empty DB exists.
2. Download the latest backup zip from Google Drive and unzip it — it contains
   `db-dumps/mysql-zoher.sql` and the `storage/app` files.
3. Load the dump:
   ```bash
   docker compose exec -T db mysql -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" < mysql-zoher.sql
   ```
4. Copy any `storage/app` files back into the project's `storage/app`.

> The backup zip is **AES-encrypted** if `BACKUP_ARCHIVE_PASSWORD` is set in
> `.env`. Keep that password somewhere safe and separate — without it the backup
> can't be opened. It is currently unset (no encryption).
