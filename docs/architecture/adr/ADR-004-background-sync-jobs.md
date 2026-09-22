# ADR-004 — Background sync jobs

- **Status:** Accepted
- **Date:** 2026-09-22

## Context

Okyema needs reliable connector synchronisation, webhook handling and
automation runs. SIKA wires Laravel's database queue but has no custom job
classes.

## Decision

Use SIKA's existing queue mechanism (`QUEUE_CONNECTION=database`) and add
idempotent, observable, retryable job classes with:

- a per-connector-account concurrency lock;
- persisted `sync_cursors` so jobs resume safely;
- duplicate-webhook protection via idempotency keys;
- structured `sync_runs` records with health and last-success timestamps.

## Consequences

- Sync can be retried safely and leaves an audit trail.
- Webhook delivery duplication is handled, not assumed away.
