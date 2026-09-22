# Okyema — Release Checklist

Run top to bottom before tagging a production release.

## Before the release

- [ ] `php -l` clean on changed files
- [ ] `php vendor/bin/pint --test` clean
- [ ] `php vendor/bin/pest` green
- [ ] `npx playwright test` green (explicit request — not part of routine work)
- [ ] Migrations reviewed: any irreversible ones are called out
- [ ] `.env.example` matches every `env()` key used, with descriptions and no secrets
- [ ] Brand assets (O/K monogram, icons, splash) present in `public/assets/`
- [ ] Threat model and runbooks updated for any connector/scope change

## Cut the release

- [ ] `php artisan okyema:release patch|minor|major` (bumps the version — the
      `?v=` cache-buster — runs the suite, commits and tags)
- [ ] Push `main` to trigger the deploy workflow

## After the release

- [ ] `/api/connectors` health checked (no `needs_reauth`, no `error`)
- [ ] Backup taken and a restoration test run (see
      `docs/operations/BACKUP_AND_RESTORE.md`)
- [ ] Release notes appended to `docs/PROGRESS.md`
- [ ] Known limitations updated
