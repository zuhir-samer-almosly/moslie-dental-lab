---
name: deploy
description: Prepare a change for release to production (dental-lab.zoher-moslie.me) and hand off the exact VPS commands — the user runs everything server-side themselves. Use when asked to deploy, release, push to production, "put this live", or when someone asks why a change they made isn't showing up on the live site.
---

# Releasing moslie-dental-lab

Production is a DigitalOcean VPS running the `docker-compose.yml` stack
(`caddy` → `app` → `db` + `redis`) behind Caddy-managed Let's Encrypt certs.
Reference: `DEPLOYMENT.md`. Related memory: `deployed-on-do-vps`.

**The user owns the VPS and runs every server-side command themselves.** Don't
attempt to SSH, and don't guess at server paths or state — `DEPLOYMENT.md` is
inconsistent about the clone location, so quote commands relative to the repo
directory and let the user supply the rest. Your job is everything up to the
handoff: make sure what's being shipped is green, pushed, and complete, and tell
the user precisely what the deploy will do to production.

## Pre-flight (local — this is the part you actually do)

1. **Run the quality gate** via the `run-checks` skill (Pest + Pint + `npm run types`).
   A broken build is expensive to discover on the server: it costs a full rebuild
   cycle on a 1 vCPU droplet.
2. **Commit and push.** The VPS deploys by `git pull`, so anything uncommitted or
   unpushed will not ship. Verify with `git status` and `git log origin/main..HEAD`.
3. **Say what's in the diff.** Before handing off, tell the user explicitly:
   - whether it includes **migrations** (`git diff --name-only origin/main -- database/migrations/`)
     — those change the schema and are the hard part to undo;
   - whether it changes **`composer.json`** — that needs an extra `package:discover`
     step (see below), and a package with system dependencies (Browsershot needing
     Chromium, say) may need `Dockerfile` changes shipped alongside it;
   - whether it adds **new config keys**. `.env.docker` is gitignored
     (`.gitignore:31`) and the container reads a bind-mounted host `./.env`
     (`docker-compose.yml:29`), so the production `.env` exists only on the
     server. A new key has to be added there by hand or the app deploys and
     quietly reads a null. See the `env-docker-secrets-exposed` memory.

## Handoff

```bash
git pull
docker compose build app     # rebuilds PHP deps + Vite assets; the slow step
docker compose up -d
docker compose ps
```

`DEPLOYMENT.md` was written against the v1 `docker-compose` binary; the `backups`
skill assumes v2 (`docker compose`, space) on this box.

## Facts worth knowing (these explain most production surprises)

**Every change needs a rebuild — including frontend-only ones.** The image bakes
the app in at build time: `composer install` at `Dockerfile:51`, `npm run build`
at `Dockerfile:68`, source copied at `Dockerfile:62`. Production mounts only
`.env`, `storage`, and `bootstrap/cache` — never the code. A `restart` or a bare
`up -d` re-runs the old image, so the change silently doesn't appear. This is the
single most common "why isn't my fix live?" cause.

**Migrations run themselves on container start.** `docker/entrypoint.sh` runs
`php artisan migrate --force` unless `RUN_MIGRATIONS=false`, so the manual migrate
step still listed in `DEPLOYMENT.md` predates the entrypoint. The catch: it waits
~3 minutes for MySQL and, if the DB is still unreachable, **logs a warning and
boots the app with migrations skipped**. When a release carries a migration, it's
worth confirming rather than assuming:

```bash
docker compose logs app | grep -i "migrat"   # want "Running migrations...", not "skipping migrations"
docker compose exec app php artisan migrate --force   # if they were skipped
```

**Caches hold old code paths.** After a deploy: `docker compose exec app php artisan optimize`.

**A new Composer package needs `package:discover` on top of the rebuild.**
`bootstrap/cache` is bind-mounted from the host (`docker-compose.yml:31`), so its
stale `packages.php` shadows the manifest built into the fresh image and the new
package's service provider and commands don't register — the symptom is a command
that's "not defined" despite being installed. So when the diff touches
`composer.json`, the handoff needs one more line:

```bash
docker compose exec app php artisan package:discover
```

This bit the `spatie/laravel-backup` rollout; see the `deployed-on-do-vps` memory.

**Rolling back code does not roll back schema.** Redeploying an older commit
leaves any applied migration in place. If the migration is destructive and the
data matters, restore from the nightly Google Drive backup first — see the
`backups` skill.

## Verifying a release

Hard-refresh https://dental-lab.zoher-moslie.me (Vite bundles get hashed names,
but the HTML shell can be cached), exercise the change, and check one money path —
invoices and dentist balances are where a bad `Order::billable()` change surfaces.
Registration is disabled, so smoke-testing needs an existing login or the
`manage-users` skill. On a 500: `docker compose logs -f app`.

## How to suggest it

Give exact copy-pasteable one-liners rather than prose descriptions of steps —
the user is pasting these into an SSH session. Deploying is outward-facing and
awkward to undo, so lead with what the release contains (migrations? new env
keys?) instead of just handing over commands.
