# Deployment

Production is **https://dental-lab.zoher-moslie.me**, running on a Contabo VPS
(Ubuntu, 8 GB RAM) as a Docker Compose stack:

```
caddy  ──▶  app  ──▶  db     (MySQL 8)
(TLS)      (nginx +   redis
            php-fpm +
            supervisor)
```

| | |
|---|---|
| Host user | `deploy` (in the `docker` group; `sudo` for apt/ufw only) |
| Project path | `/var/www/moslie-dental-lab` |
| Compose file | `docker-compose.yml` (v2 syntax — `docker compose`, with a space) |
| Second site | `tracking-app.zoher-moslie.me` → `financeapp:3000`, via `docker/caddy/conf.d/` |

The same Caddy container fronts both sites. Anything in
`docker/caddy/conf.d/*.caddy` is gitignored on purpose — see that directory's
README.

---

## Shipping a change

This is the everyday path. Use the **`deploy` skill**, which covers the
pre-flight (green checks, pushed, what's in the diff) before you get here.

```bash
cd /var/www/moslie-dental-lab
git pull
docker compose build app     # the slow step: composer install + npm run build
docker compose up -d
docker compose logs -f app   # watch migrations run
docker compose exec app php artisan optimize
```

Four things that explain almost every "why isn't my change live?":

1. **Every change needs a rebuild — including frontend-only ones.** The image
   bakes the app in at build time (`composer install`, `npm run build`,
   `COPY . .`). Production mounts only `.env`, `storage`, and `bootstrap/cache`
   — never the code. A `restart`, or a bare `up -d` without `build`, re-runs the
   old image.
2. **Migrations run themselves** from `docker/entrypoint.sh` on container start
   (set `RUN_MIGRATIONS=false` to skip). Don't run `migrate` by hand right after
   `up -d` — you'll race the entrypoint's own wait loop. Confirm instead:
   `docker compose logs app | grep -i migrat`.
3. **A new Composer package needs `package:discover`.** `bootstrap/cache` is
   bind-mounted, so its stale `packages.php` shadows the manifest in the fresh
   image: `docker compose exec app php artisan package:discover`.
4. **A new config key must be added to the server's `.env` by hand.** `.env` is
   gitignored and exists only on the VPS; a missing key deploys as a silent
   null.

---

## Standing up a new server

### 1. Prepare the host

```bash
sudo reboot                          # if the login banner asks for one
sudo apt update && sudo apt upgrade -y
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker deploy       # then log out and back in
docker run --rm hello-world          # confirms the group took effect

sudo ufw allow OpenSSH
sudo ufw allow 80/tcp && sudo ufw allow 443/tcp && sudo ufw allow 443/udp
sudo ufw enable
```

Only 80/443 are published. **MySQL and Redis are deliberately not** — Docker
publishes ports *past* UFW, so a published `3306` would be open to the internet
regardless of the firewall. The app reaches them over the internal
`moslie-network`. If you ever need host access, bind to `127.0.0.1` only.

On a host with less than ~2 GB RAM, add swap before building: `npm run build`
(Vite + the React Compiler) is the memory-hungry step.

### 2. Clone the repo

The repo is private, so the box needs its own key:

```bash
ssh-keygen -t ed25519 -C "$(hostname)" -f ~/.ssh/id_ed25519 -N ""
cat ~/.ssh/id_ed25519.pub    # add at GitHub → repo → Settings → Deploy keys (read-only)

sudo mkdir -p /var/www && sudo chown deploy:deploy /var/www
cd /var/www
git clone git@github.com:zuhir-samer-almosly/moslie-dental-lab.git
cd moslie-dental-lab
```

### 3. Create `.env`

```bash
cp .env.docker.example .env
chmod 644 .env
nano .env
```

Fill in `APP_KEY` (`docker compose run --rm app php artisan key:generate` on a
*new* install — **never** on a restore, see below), `DB_PASSWORD`,
`DB_ROOT_PASSWORD`, `ACME_EMAIL`, and the `GOOGLE_DRIVE_*` backup keys from
`BACKUPS.md`.

Two traps in this one file:

- **It must be mode 644.** Inside the container php-fpm, the queue worker and
  the scheduler all run as `www-data` (uid 82), while the file is owned by
  `deploy` (uid 1000). A `600` `.env` is unreadable to them, and dotenv fails
  **silently** — Laravel falls back to config defaults (`database` cache store,
  no `APP_KEY`) instead of erroring.
- **It must exist as a file before `up`,** or Docker creates a *directory* at
  the mount point and the app boots with no config at all.

Compose also reads this file for its own `${...}` substitution, which is where
`DB_PASSWORD`, `DB_ROOT_PASSWORD` and `ACME_EMAIL` come from.

### 4. Other sites on the box

```bash
cp /path/to/tracking-app.caddy docker/caddy/conf.d/
```

The upstream container must join `moslie-network` or Caddy can't resolve it.
`caddy validate` passes on an unresolvable upstream — only a real request
proves it works.

### 5. DNS

Point the records at the new IP **before** starting Caddy — Let's Encrypt
validates over HTTP-01, so the names must already resolve to this box.

| Record | Name | Value |
|---|---|---|
| A | `dental-lab.zoher-moslie.me` | server IP |
| A | `tracking-app.zoher-moslie.me` | server IP |
| AAAA | either name | the server's IPv6 — **or no AAAA at all** |

On Cloudflare, keep **proxy status "DNS only"** (grey cloud). Proxied, Cloudflare
terminates TLS itself and Caddy never completes its ACME challenge.

A stale `AAAA` pointing at an old server is the classic silent failure: IPv6
clients keep hitting the old box while IPv4 looks perfect. Delete or update it.

```bash
dig +short dental-lab.zoher-moslie.me
dig +short AAAA dental-lab.zoher-moslie.me
```

### 6. Build and start

```bash
docker compose build
docker compose up -d
docker compose logs -f app
docker compose logs caddy | grep -i "certificate\|acme\|error"
```

A fresh MySQL volume can take several minutes to initialize on first boot; the
entrypoint polls for it (~5 minutes) before running migrations.

### 7. Restore the data (skip for a genuinely new install)

Get a dump — either `mysqldump` from the old server, or the latest
`spatie/laravel-backup` zip from Google Drive (`db-dumps/mysql-zoher.sql`).
It contains `DROP TABLE IF EXISTS` for every table **including `migrations`**,
so it can be loaded straight over the schema the entrypoint just created.

```bash
cd /var/www/moslie-dental-lab
source <(grep -E '^DB_(ROOT_PASSWORD|DATABASE)=' .env)
docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASSWORD" "$DB_DATABASE" < dump.sql

docker compose restart app     # entrypoint re-runs migrate for anything newer
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan optimize
```

Also copy any `storage/app` files across.

- **Keep the old `APP_KEY`.** Don't run `key:generate` on a restore — the
  restored data is keyed to it.
- **Don't run `ledger:rebuild`.** The journal comes over in the dump and is
  already correct. Rebuilding without `--cash-on-hand` would drop the opening
  balance.

### 8. Verify

```bash
docker compose exec app php artisan tinker --execute="
  echo 'cache store: '.config('cache.default').PHP_EOL;
  echo 'dentists: '.\App\Models\Dentist::count().PHP_EOL;
  echo 'orders: '.\App\Models\Order::count().PHP_EOL;
  echo 'journal lines: '.\App\Models\JournalLine::count().PHP_EOL;"

curl -sSL -o /dev/null -w '%{http_code}\n' https://dental-lab.zoher-moslie.me
docker compose exec app php artisan backup:list
docker compose exec app php artisan backup:run
```

`cache store: redis` is the tell that `.env` is being read. Then log in and
check the dashboard totals, one invoice PDF (exercises Chromium + the Arabic
font), and outstanding balances.

Public registration is disabled — create logins with
`docker compose exec app php artisan app:create-user` (or the `manage-users`
skill), not through the UI.

When migrating, leave the old server running for a few days.

---

## How file ownership works here

Worth understanding, because it causes the most confusing failures.

`storage` and `bootstrap/cache` are bind-mounted from the host, which
overwrites the ownership the Dockerfile set at build time. So
`docker/entrypoint.sh` chowns both to `www-data` at runtime — **twice**: once on
entry, and again after migrations. The second pass matters because the DB wait
loop and `migrate` run as **root**, and anything they create (notably
`storage/logs/laravel.log`) would otherwise stay root-owned. supervisord then
starts the queue worker and scheduler as `www-data`, which crash-loop on a log
file they can't append to.

---

## Backups

Nightly off-site backups to Google Drive via `spatie/laravel-backup`, scheduled
in `routes/console.php` and run by the `laravel-schedule` supervisor program.
Full setup, restore procedure and the OAuth token trap: **`BACKUPS.md`** and the
`backups` skill.

The `db-backup.sh` / `db-backup-auto.sh` scripts in the repo root are **local
dev only** (Windows + WSL + OneDrive). Don't use them on the VPS.

---

## Troubleshooting

| Symptom | Cause |
|---|---|
| `laravel-queue_00 entered FATAL state`, `laravel.log ... Permission denied` | Root-owned log file. Fixed by the entrypoint's second chown; if it recurs, `docker compose exec app rm -f storage/logs/laravel.log && docker compose restart app` |
| Queries hit a `cache` table when `CACHE_STORE=redis` | `.env` unreadable by `www-data` → dotenv fell back to defaults. `chmod 644 .env` |
| `Database not reachable after N attempts; skipping migrations` | MySQL still initializing. It's usually up by now: `docker compose restart app` |
| A code change doesn't appear | No rebuild. `docker compose build app && docker compose up -d` |
| New artisan command "is not defined" | Stale bind-mounted `bootstrap/cache`. `php artisan package:discover` |
| No certificate issued | DNS not pointing here yet, Cloudflare proxy on, port 80/443 blocked, or an ACME rate limit (wait an hour). `docker compose logs caddy \| grep -i acme` |
| `tracking-app` 502s | `financeapp` isn't running or isn't on `moslie-network` |
| `ledger:rebuild` prints "Command Cancelled" | `APP_ENV=production` triggers the destructive-command guard. Pass `--force` — and `--cash-on-hand=N` on *every* rebuild |
| `backup:list` says `File not found` | An expired Google refresh token, not a missing Drive folder. See `BACKUPS.md` |

General log access:

```bash
docker compose ps
docker compose logs -f app          # or: caddy, db, redis
docker compose exec app tail -50 storage/logs/laravel.log
docker stats
df -h && docker system df
```
