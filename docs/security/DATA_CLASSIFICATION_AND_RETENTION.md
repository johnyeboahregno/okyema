# Okyema — Data Classification and Retention

## Classification

| Class | Examples | Handling |
|-------|----------|----------|
| Confidential | Regno/Launchpad financials, decisions, private notes | Workspace-scoped; only the owner; AI summaries cite but never exfiltrate |
| Personal | Personal calendar, receipts | Workspace-scoped (`PERSONAL`); never mixed into Regno/Launchpad results |
| Sensitive | OAuth tokens | Encrypt-at-rest; never logged or returned to the client |
| Metadata | Sync cursors, run statuses | No personal content; structured logs only |

## Retention

- **Receipt originals** — kept unchanged for as long as the linked expense is
  retained; derivatives (OCR/enhancement) are separate files.
- **AI inputs** — stored as bounded summaries (`AIRunLogger` truncates input to
  2,000 chars) for reproducibility; full transcripts are never logged.
- **Audit trail** (`action_audits`, `sync_runs`, `automation_runs`) — append-only;
  retained for the life of the account.
- **Disconnected connectors** — revoking a connector deletes its scoped content
  and tokens according to the per-provider runbook (`docs/connectors/`).

## Deletion

Removing a connector triggers the configured retention/deletion workflow:
tokens are dropped immediately, provider-linked records are soft-deleted where
sync recovery requires it and hard-deleted otherwise, and `audit_events` record
the removal.
