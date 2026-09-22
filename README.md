# Okyema

**Your intelligent chief of staff. Powered by Regno AI.**

Okyema is a production-grade, mobile-first executive operating system. It
unifies meetings, communications, commitments, tasks, calendars, travel,
expenses, receipts, documents and important events into one secure,
date-centred system — with strict separation between **Regno**, **Launchpad**
and **Personal** contexts.

Built to the same architecture and patterns as the SIKA reference
implementation: **Laravel 11 + Vue 3 (CDN, no build step) + MySQL/SQLite**,
session-based auth with Sanctum for the API.

## Stack

| Layer | Tech |
|-------|------|
| Backend | PHP 8.3+, Laravel 11 |
| Frontend | Vue 3 (CDN), plain JS, single CSS file |
| Database | SQLite (local) / MySQL (production) |
| Auth | Laravel session (web) + Sanctum (API) |
| AI | Shared `courtly/ai-client` package (Regno AI) |
| Money | Integer minor units (pence) — never floats |

## Quick start (local)

```bash
cd okyema
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # if it does not exist
php artisan migrate --seed
php artisan serve
```

Open `http://localhost:8000`.

> **Note on `vendor/`**: this checkout shares the SIKA/study-fund installed
> dependencies to work without Composer on the dev machine. On a fresh machine
> or the VPS, run `composer install` instead (see `composer.json`).

## Shared package

The generic AI plumbing lives in the shared `courtly/ai-client` package at
`../ai-client` (path repository in `composer.json`). Okyema keeps thin shims
(`App\Services\AI\AIProviderInterface` + `OpenAiCompatibleProvider`) that
extend the shared classes, and registers a manual autoloader in
`AppServiceProvider` for machines without Composer.

## Workspace contexts

Every stored object carries a workspace identifier — `REGNO`, `LAUNCHPAD` or
`PERSONAL` — unless it is explicitly global. The three default contexts are
created for the first user, and the UI always shows the active context. See
`docs/architecture/adr/ADR-001-workspace-contexts.md`.

## Environment

See `.env.example`. Key values:

- `DB_CONNECTION` — `sqlite` locally, `mysql` on the VPS
- `SESSION_DRIVER` — `file`
- `SANCTUM_STATEFUL_DOMAINS` — your domain(s), comma-separated
- `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` — optional OAuth

## Brand assets

Okyema uses the supplied Okyema asset pack (the approved O/K monogram, app
icon, splash and PWA icons). Those files are **not** committed to this
repository and must be placed in `public/assets/` before release:

- `icon-192.png`, `icon-512.png`, `icon-maskable-512.png`
- `apple-touch-icon.png`, `favicon.png`
- the approved O/K monogram used by `app.php`, `login.php`, `register.php`

The in-code monogram is a temporary placeholder only — the approved mark is
never redrawn or reinterpreted.

## Tests

```bash
php artisan test
```

SQLite in-memory is used for tests (`phpunit.xml`), so no database server is
needed. Browser end-to-end tests (Playwright) run on demand with
`npm run test:e2e`.

## Documentation

- [Product brief](docs/product/PRODUCT_BRIEF.md)
- [SIKA architecture baseline](docs/architecture/SIKA_ARCHITECTURE_BASELINE.md)
- [Okyema architecture plan](docs/architecture/OKYEMA_ARCHITECTURE_PLAN.md)
- [Architecture decision records](docs/architecture/adr/)
- [Threat model](docs/security/THREAT_MODEL.md)
- [Data classification & retention](docs/security/DATA_CLASSIFICATION_AND_RETENTION.md)
- [Operations runbook](docs/operations/RUNBOOK.md)
- [Backup & restore](docs/operations/BACKUP_AND_RESTORE.md)
- [Test strategy](docs/testing/TEST_STRATEGY.md)
- [Deployment](docs/deployment/DEPLOYMENT.md)
- [Release checklist](docs/deployment/RELEASE_CHECKLIST.md)
- [Connectors overview](docs/connectors/README.md) · [Google](docs/connectors/google.md) · [Microsoft](docs/connectors/microsoft.md) · [Slack](docs/connectors/slack.md)
- [Progress log](docs/PROGRESS.md)
