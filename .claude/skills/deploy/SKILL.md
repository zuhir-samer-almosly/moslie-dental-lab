---
name: deploy
description: Deploy moslie-dental-lab to production. Use when asked to deploy, ship, "push it live", or update the production site at dental-lab.zoher-moslie.me. Clarifies that a GitHub push alone does NOT deploy — production needs a Docker rebuild on the VPS.
---

# Deploying moslie-dental-lab

Production runs via Docker on a DigitalOcean VPS, served at
**dental-lab.zoher-moslie.me** (behind Caddy). **Pushing to GitHub `main` does not
deploy anything** — the running container must be rebuilt on the VPS, and any new
migrations must be run there.

## Step 1 — get the code on GitHub (from this machine)
Confirm with the user before pushing. They commit directly to `main` here.
```bash
git push origin main
```
- The Dockerfile builds frontend assets (`npm run build`) during image build, so new
  React pages ship automatically once the image is rebuilt.
- Commit message footer: `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.

## Step 2 — deploy on the VPS (the user runs this; you don't have SSH access)
On the server, in the project directory (`/var/www/moslie-dental-lab`):
```bash
git pull && docker compose build app && docker compose up -d && docker compose exec app php artisan migrate --force
```
- `migrate --force` is **required** whenever the release added tables/columns
  (e.g. `employees`, `employee_payments`, `material_purchases`) — without it the new
  pages 500. APP_ENV=production, hence `--force`.
- If `docker compose build app` errors with "no such service", run
  `docker compose config --services` and use the real service name.
- If behavior looks stale after deploy:
  `docker compose exec app php artisan optimize:clear`.

## How to suggest it
The user typically runs the deploy command themselves on the VPS. Offer the exact
one-liner from Step 2 and remind them about the `migrate --force` tail if the release
changed the schema. Related memory: `deployed-on-do-vps`.
