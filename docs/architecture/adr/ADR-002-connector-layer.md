# ADR-002 — Connector layer

- **Status:** Accepted
- **Date:** 2026-09-22

## Context

Okyema talks to Google Calendar, Gmail, Drive, Photos, Outlook Calendar and
Email, OneNote, Teams, Slack, Granola, Linear and LINE. SIKA has one OAuth
integration (Google sign-in) and no provider sync.

## Decision

Every external service is a **connector adapter** living in
`App\Services\Connectors\*`, exposing canonical capabilities
(`calendar.read`, `mail.draft`, `files.write`, …) plus OAuth lifecycle,
cursor/checkpoint storage, retry with backoff and jitter, and normalisation
into canonical models. Domain services and the UI never depend on a provider
SDK.

## Consequences

- One Google and one Microsoft vertical slice prove the interface first.
- Adding a provider never touches domain logic.
- Idempotent sync and structured `SyncRun` records become the norm.
