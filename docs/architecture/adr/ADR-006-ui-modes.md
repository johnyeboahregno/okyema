# ADR-006 — Two interface modes selected by environment

- **Status:** Accepted
- **Date:** 2026-09-24

## Context

Okyema ships one Vue 3 shell (`resources/views/app.php`) served from the
`/` dashboard route. We want a second, radically simpler assistant screen
alongside it, chosen per deployment rather than per user.

## Decision

`OKYEMA_UI_MODE=classic|simple` selects the shell at the route boundary.
The `/` route branches on `App\Support\UiMode::resolve()` and includes either
`resources/views/simple.php` or `resources/views/app.php`. The value is read
at runtime from `config('okyema.ui.mode')` — there is no build step, so a
change to the variable takes effect on the next PHP process (or after
`php artisan config:cache` if that is used). Invalid or missing values fall
back to `classic` and are reported through the application log.

Both modes share the same `/api/*` routes, authentication, workspace
contexts, Notion connection, AI provider credentials and approval rules.
`simple` mode adds no domain services of its own — it reuses the assistant,
Notion and approval services introduced alongside it.

## Consequences

- Direct links and refreshes work in both modes (single `/` route).
- `classic` is preserved visually and functionally.
- The mode is not a user toggle, account preference or query parameter.
- `simple` mode's typed and spoken requests flow through `POST /api/assistant`,
  which grounds answers in the active context (including Notion) and routes
  any proposed Notion change through a pending `ApprovalRequest`.
