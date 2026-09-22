# Okyema — Connectors

Every external service is a connector adapter behind a capability interface.
Domain code and the UI never see a provider SDK (ADR-002).

## Capability model

`calendar.read`, `calendar.write`, `mail.read`, `mail.draft`, `mail.send`,
`chat.read`, `chat.draft`, `chat.send`, `files.read`, `files.write`,
`notes.read`, `notes.write`, `photos.read_selected`, `meetings.import`.

## Targets

| Connector | Adapter | Capabilities | Status |
|-----------|---------|--------------|--------|
| Google Calendar | `GoogleCalendarConnector` | `calendar.read` | implemented (guarded) |
| Microsoft Calendar | `MicrosoftCalendarConnector` | `calendar.read` | implemented (guarded) |
| Google Drive | `DriveFilesConnector` | `files.read`, `files.write` | implemented (guarded) |
| Gmail / Outlook | `MailConnector` | `mail.read`, `mail.draft`, `mail.send` | implemented (guarded) |
| Slack / Teams | `ChatConnector` | `chat.read`, `chat.draft`, `chat.send` | implemented (guarded) |
| OneNote, Photos, Granola, Linear, LINE | — | — | planned (Milestone 4+ refinement) |

## Conventions per connector

- OAuth lifecycle with requested/granted scopes stored on `connector_accounts`.
- Cursor/checkpoint sync (`sync_cursors`) with idempotent upserts.
- Retry with backoff/jitter; idempotency keys; structured, redacted logs.
- Health + last-success timestamps surfaced via `/api/connectors`.

Each adapter refuses loudly without credentials — it never fakes a successful
sync, send or upload.
