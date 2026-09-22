# Microsoft connectors — setup, scopes, sync, troubleshooting

Covers Outlook Calendar, Outlook Email, Teams (and OneNote where permitted).

## Setup

1. Register an app in **Microsoft Entra** (Azure AD) → App registrations.
2. Set the redirect URI (web) to `{APP_URL}/auth/microsoft/callback`.
3. Add API permissions (delegated) — see scopes below — and grant admin
   consent if the tenant requires it.
4. Put the client id/secret in `.env` (`MICROSOFT_CLIENT_ID`,
   `MICROSOFT_CLIENT_SECRET`, `MICROSOFT_REDIRECT_URI`).

## Scopes (least privilege)

| Capability | Scope |
|-----------|-------|
| `calendar.read` | `Calendars.Read` |
| `mail.read` | `Mail.Read` |
| `mail.draft` | `Mail.ReadWrite` (draft) |
| `mail.send` | `Mail.Send` |
| `chat.read` (Teams) | `Chat.Read` |
| `notes.read` (OneNote) | `Notes.Read` |

## Sync

- `MicrosoftCalendarConnector` uses the Graph calendarView **delta query**;
  the `$deltatoken` is persisted in `sync_cursors` and events upsert
  idempotently by `(provider, provider_event_id)`.

## Troubleshooting

- **`invalid_grant` / token expired** — refresh the token or reconnect the
  account; `ConnectorStatus::NeedsReauth` is surfaced in `/api/connectors`.
- **403 on Graph** — a required delegated permission is missing or admin
  consent was not granted.
