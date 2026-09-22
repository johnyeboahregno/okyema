# Okyema — Backup and Restore

## What to back up

1. **Database** — SQLite (`database/database.sqlite`) locally, MySQL on the VPS.
2. **Receipt originals** — `storage/app/receipts/` (private disk).

Provider-derived content is re-syncable from cursors, but the canonical DB and
the receipt originals are not.

## Backup (local SQLite)

```bash
# database
cp database/database.sqlite "backups/okyema-$(date +%F).sqlite"

# receipt originals
tar -czf "backups/okyema-receipts-$(date +%F).tgz" storage/app/receipts
```

On the VPS, use `mysqldump` for the MySQL database instead.

## Restore

```bash
# stop the app, then
cp "backups/okyema-2026-09-22.sqlite" database/database.sqlite
tar -xzf "backups/okyema-receipts-2026-09-22.tgz" -C storage/app/
php artisan migrate --force   # idempotent, catches schema drift
```

## Tested restoration

Restoration is exercised on release: restore the latest backup to a scratch
instance, run `php artisan migrate --force`, then `php vendor/bin/pest` and a
smoke login.
