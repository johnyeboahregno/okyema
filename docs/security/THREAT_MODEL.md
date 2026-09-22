# Okyema — Threat Model

Okyema connects to calendars, mail, chat, files and notes, so its threat
model is dominated by connector risk. This is a living document; update it
when a connector or capability changes.

## Assets

| Asset | Sensitivity | Location |
|-------|-------------|----------|
| OAuth access/refresh tokens | High | `connector_accounts` (hidden from JSON; encrypt-at-rest column) |
| Workspace content (Regno/Launchpad/Personal) | High | domain tables, scoped by `workspace_context_id` |
| Receipt originals | Medium | private `local` disk, `receipts/…` |
| AI prompts/outputs | Medium | `ai_runs`, `summaries`, `decisions` (bounded input summaries) |
| Audit trail | High (integrity) | `action_audits`, `sync_runs`, `automation_runs` |

## Threat scenarios and mitigations

| # | Threat | Mitigation |
|---|--------|-----------|
| 1 | OAuth token compromise | Tokens hidden from API responses, stored server-side, encrypt-at-rest column, per-account scope grant, revoke on disconnect |
| 2 | Confused deputy (a connector acting beyond its grant) | Capability allowlist per connector (`calendar.read`, `mail.send`, …); domain code never calls provider SDKs directly |
| 3 | Cross-context leakage | Every domain query filters `workspace_context_id`; non-members get 404; context-isolation tests |
| 4 | Prompt injection via email/notes/transcripts | External content treated as data, never instructions; AI tools allowlisted and approval-gated |
| 5 | Malicious attachments | Upload size/type validation; malware-scanning hook (see receipts) |
| 6 | Duplicate webhook delivery / replay | Idempotency keys, unique `(provider, provider_event_id)` and `(provider, provider_message_id)` constraints, persisted sync cursors |
| 7 | Stolen device | Session expiry, CSRF protection, Sanctum stateful domains, no long-lived tokens in browser storage |
| 8 | Excessive data retention | Retention policy in `DATA_CLASSIFICATION_AND_RETENTION.md`; soft-delete where sync requires it, explicit purge elsewhere |

## Residual risks

- Live provider upload/send paths are guarded adapters that refuse without
  credentials; they have not been exercised against real providers in this
  repository.
- The AI vision/structured-output paths are schema-validated and
  deterministic-fallback protected, but a live provider is required to prove
  them end-to-end.
