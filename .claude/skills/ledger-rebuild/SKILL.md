---
name: ledger-rebuild
description: Rebuild the double-entry ledger from the domain tables with `ledger:rebuild`. Use when asked to rebuild/regenerate/repair the ledger or journal entries, when money figures in the reports look wrong or stale, when the trial balance does not balance, or when setting or correcting the cash-on-hand opening balance.
---

# Rebuilding the ledger

`php artisan ledger:rebuild` deletes every journal entry and re-posts them from
the domain tables. It is rerunnable and the ledger mirrors current state, so a
rebuild is the repair tool when entries have drifted from the records.

## Two flags that are not optional

**`--force` is required on both local and production containers.** They run
`APP_ENV=production`, which puts the command behind Laravel's
destructive-command guard. A non-interactive `docker compose exec` answers that
prompt with the default (no) and aborts with "Command Cancelled" — which looks
like a hang or a silent failure rather than a refusal.

**`--cash-on-hand=N` must be supplied on *every* rebuild, not just the first.**
It posts the gap between the real counted cash and the computed cash box to
owner capital, dated the day before the ledger's first entry. A rerun *without*
it deletes that opening balance. The command warns loudly when that happens —
read its output, don't just check the exit code.

## Invocation

Local:

```bash
docker compose -f docker-compose.local.yml exec app \
  php artisan ledger:rebuild --force --cash-on-hand=<counted cash>
```

Production: the user runs server-side commands themselves — hand off the
equivalent `docker compose exec` line rather than attempting it. See the
`deploy` skill and `DEPLOYMENT.md`.

## Afterwards

Confirm the trial balance balances and that per-dentist outstanding figures
still reconcile; `Ledger::post()` throws `UnbalancedEntryException` on any
entry whose debits and credits disagree, so a completed run means each
individual entry balanced, not that the totals are what you expected.
