# Okyema — Deployment

Okyema follows the SIKA deployment pattern: a shared Docker Compose stack
(Caddy/nginx reverse proxy + PHP app container + MySQL + a queue worker).

## Environment

Production domain: **`https://john.okyema.work`**.

Copy `.env.example` to `.env` and set, at minimum:

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://john.okyema.work`
- `DB_CONNECTION=mysql` and the MySQL credentials
- `SANCTUM_STATEFUL_DOMAINS=john.okyema.work`
- `GOOGLE_REDIRECT_URI=https://john.okyema.work/auth/google/callback`
  (registered verbatim in the Google Cloud console)

See `GOOGLE_SIGNIN.md`-style notes in `.env.example`.

## Build & release

```bash
php artisan okyema:release patch   # bumps version, runs tests, commits + tags
```

The push to `main` triggers the deploy workflow. The version number is the
`?v=` cache-buster for the CSS and PWA icons, so it moves on every release.

## Deploy (VPS)

Okyema ships the same `deploy.sh` + `docker/` setup as SIKA, under the
`okyema-app` and `okyema-queue` Compose services:

```bash
bash ~/okyema/deploy.sh
```

The script pulls `master`, rebuilds `okyema-app` + `okyema-queue`, recreates
them, runs `php artisan migrate --force`, then emails the deploy summary via
`php artisan mail:deploy-success`.

- `docker/php/Dockerfile` — multi-stage: Composer installs `vendor/`, the PHP
  8.4 FPM image runs nginx + supervisor + php-fpm.
- `docker/php/entrypoint.sh` — re-asserts storage ownership on every start.
- `docker/nginx/default.conf` — routes everything through `public/index.php`.

The shared Compose stack lives at `$HOME/courtly` (`COMPOSE_DIR`) and also runs
the reverse proxy and the `okyema-mysql` database. Point Caddy at
`okyema-app:80` for the `john.okyema.work` domain.

## Health and rollback

- `/api/connectors` shows connector health; readiness is checked before swap.
- Rollback = redeploy the previous tag and `php artisan migrate --rollback`
  only when the migration is backward-compatible; otherwise restore the
  database backup (`docs/operations/BACKUP_AND_RESTORE.md`).
- CI runs `pint --test` and `pest` on every push and pull request.
