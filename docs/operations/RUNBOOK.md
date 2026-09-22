# Okyema — Operations Runbook

## Local development

```bash
cd okyema
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan db:seed --class=Database\\Seeders\\DemoSeeder   # optional demo user
php artisan serve
```

Sign in as `john@okyema.test` / `password` (demo) or register a new account.

## Verification

```bash
php -l <file>                    # syntax
php vendor/bin/pint --test       # style
php vendor/bin/pest              # tests (SQLite :memory:, no DB server)
```

## Connector health

`GET /api/connectors` reports each connected account's provider, status,
capabilities and last-sync time without exposing tokens.

## Queue / jobs

`QUEUE_CONNECTION=database` is wired. Run the worker on the VPS:

```bash
php artisan queue:work
```

Sync and automation jobs are idempotent and resumable from persisted cursors.

## Logs

Logs are structured and redacted (`storage/logs/laravel.log`). No tokens or
inappropriate personal content are logged. Correlation IDs are added across
UI, API, jobs and connector calls.
