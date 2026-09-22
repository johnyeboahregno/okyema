# Slack connector — setup, scopes, sync, troubleshooting

## Setup

1. Create a Slack app at `api.slack.com`.
2. Under **OAuth & Permissions**, add the redirect URI
   `{APP_URL}/auth/slack/callback`.
3. Install to the workspace and store the bot/user token server-side.

## Scopes (least privilege)

| Capability | Scope |
|-----------|-------|
| `chat.read` | `channels:history`, `groups:history`, `im:history` |
| `chat.draft` | — (drafts are local until approved) |
| `chat.send` | `chat:write` |

## Sync

- Messages are normalised into `conversations` / `message_references` with a
  unique `(provider, provider_message_id)` for idempotent delivery.
- Sending is approval-gated: a `Draft` plus an `ApprovalRequest` must be
  explicitly approved before `ChatConnector` posts.

## Troubleshooting

- **`not_authed`** — token revoked; reconnect the account.
- **`missing_scope`** — reinstall the app after adding the scope.
