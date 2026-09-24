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

See `GOOGLE_SIGNIN.md`-style notes in `.env.example`, and the full sign-in
runbook in [`GOOGLE_SIGNIN.md`](GOOGLE_SIGNIN.md).

## Interface mode

`OKYEMA_UI_MODE` selects which shell the dashboard renders for the whole
deployment:

- `classic` — the full workspace SPA (default).
- `simple` — the focused assistant screen.

The value is read at runtime by the PHP shell (there is no build step). Set
it in `.env` before deploy; an invalid or missing value falls back to
`classic` and is logged.

```bash
OKYEMA_UI_MODE=classic   # the full workspace SPA
OKYEMA_UI_MODE=simple    # the focused assistant screen
```

Notion is optional and configured in `.env`:

- `NOTION_CLIENT_ID` / `NOTION_CLIENT_SECRET` / `NOTION_REDIRECT_URI` — the
  public integration OAuth credentials (Settings → Connections → Notion).
  Register the redirect URI
  `https://john.okyema.work/connectors/notion/callback` verbatim in the
  Notion integration.
- `NOTION_DATABASE_REGNO` / `NOTION_DATABASE_LAUNCHPAD` /
  `NOTION_DATABASE_PERSONAL` — one database id per workspace context, used for
  the assistant's Notion search and approval-gated page writes.

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

### Shared-stack prerequisites (one-time VPS setup)

The `okyema-app`, `okyema-queue` and `okyema-mysql` services are defined in
Courtly's `docker-compose.yml` (this repo ships no Compose file of its own),
and `john.okyema.work` is fronted from Courtly's `docker/caddy/Caddyfile`.
Before the first deploy:

1. In `~/courtly/.env` set the MySQL credentials the `okyema-mysql` container
   reads: `OKYEMA_DB_USERNAME`, `OKYEMA_DB_PASSWORD`, `OKYEMA_DB_ROOT_PASSWORD`
   (see `~/courtly/.env.docker.example`).
2. In `~/okyema/.env` set the app values: `APP_ENV=production`,
   `APP_DEBUG=false`, `APP_URL=https://john.okyema.work`, `APP_KEY`
   (`php artisan key:generate --show`), and
   `DB_CONNECTION=mysql` / `DB_HOST=okyema-mysql` / `DB_DATABASE=okyema` /
   `DB_USERNAME` + `DB_PASSWORD` matching step 1. Also add
   `SANCTUM_STATEFUL_DOMAINS=john.okyema.work` and the production
   `GOOGLE_REDIRECT_URI`.
3. The `.dockerignore` keeps `vendor/`, `.env` and the SQLite dev database out
   of the production image, so `COPY . .` can't clobber the Composer-built
   `vendor/` or bake secrets in.

## Health and rollback

- `/api/connectors` shows connector health; readiness is checked before swap.
- Rollback = redeploy the previous tag and `php artisan migrate --rollback`
  only when the migration is backward-compatible; otherwise restore the
  database backup (`docs/operations/BACKUP_AND_RESTORE.md`).
- CI runs `pint --test` and `pest` on every push and pull request.
