---
name: manage-users
description: Create or reset login accounts for moslie-dental-lab. Use when asked to add a user/staff/account, "let me log in", "create an admin", "I forgot the password", or reset/change a password. Public registration is disabled — accounts only exist via the app:create-user command.
---

# Managing login accounts for moslie-dental-lab

This app is **single-tenant with shared data** and **public registration is
disabled** (`canRegister` is off; there is no `/register` flow). Every account is
created by hand with the `app:create-user` artisan command. There is no roles/permissions
layer — any account can see and edit everything (related memory: `single-tenant-shared-data`).

## Create an account

The command prompts for any value you don't pass as a flag, and marks the account
**email-verified immediately** (no SMTP is configured, so unverified accounts could
never log in).

**On the production VPS** (`/var/www/moslie-dental-lab`, the user runs this — you
have no SSH access):
```bash
docker compose exec app php artisan app:create-user \
  --name="Full Name" --email="person@example.com" --password="at-least-8-chars"
```
Run it with no flags for interactive prompts (`docker compose exec app php artisan app:create-user`).

**Locally** (Docker, see memory `local-runs-via-docker-not-herd`):
```bash
docker exec -it moslie-dental-lab-local php artisan app:create-user
```

Rules enforced by the command: `email` must be unique, `password` min 8 chars. On
success it prints `✅ Account created for <email>`.

## Reset / change a password

There is **no** dedicated reset command, and re-running `app:create-user` fails on
the unique-email rule. The `password` field uses the `hashed` cast
(`app/Models/User.php`), so assigning a plain string hashes it automatically. Use
tinker:
```bash
docker compose exec app php artisan tinker --execute="\
  \App\Models\User::where('email','person@example.com')->firstOrFail()\
    ->update(['password' => 'the-new-password']);"
```
(Local: swap in `docker exec moslie-dental-lab-local`.) Do **not** call
`Hash::make()` here — the cast would double-hash it.

## List / delete accounts
No UI for this. Use tinker: `User::pluck('email')` to list, or
`User::where('email',...)->delete()` to remove one.

## How to suggest it
The user runs production commands themselves on the VPS — hand them the exact
one-liner. Confirm the name/email/password with them before generating it, and never
invent or log a password they didn't choose.
