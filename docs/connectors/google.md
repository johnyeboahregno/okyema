# Google connectors — setup, scopes, sync, troubleshooting

Covers Google Calendar, Gmail, Google Drive and (selected) Google Photos.

## Setup

1. Create a project in Google Cloud Console → **APIs & Services**.
2. Enable **Calendar API**, **Gmail API**, **Drive API** (and **Photos Library
   API** only if the selected-import flow is wanted).
3. Under **OAuth consent screen**, add the app and mark it *Testing* (only test
   users can sign in while unpublished).
4. Under **Credentials**, create a **Web application** OAuth client and add the
   redirect URI verbatim. This is the **connector** client, so the path is
   `/connectors/google/callback` — not `/auth/google/callback` (that is the
   sign-in client, see `docs/deployment/GOOGLE_SIGNIN.md`):
   - `https://john.okyema.work/connectors/google/callback` (production)
   - `http://localhost:8000/connectors/google/callback` (local dev)
5. Put the client id/secret in `.env` (`GOOGLE_CALENDAR_CLIENT_ID`,
   `GOOGLE_CALENDAR_CLIENT_SECRET`) and the redirect URI
   (`GOOGLE_CALENDAR_REDIRECT_URI` — leave empty locally to follow the host you
   browse; set it explicitly in production).

## Scopes (least privilege, incremental)

| Capability | Scope |
|-----------|-------|
| `calendar.read` | `https://www.googleapis.com/auth/calendar.readonly` |
| `mail.read` | `https://www.googleapis.com/auth/gmail.readonly` |
| `mail.draft` | `https://www.googleapis.com/auth/gmail.compose` |
| `mail.send` | `https://www.googleapis.com/auth/gmail.send` |
| `files.read` | `https://www.googleapis.com/auth/drive.readonly` |
| `files.write` | `https://www.googleapis.com/auth/drive.file` |
| `photos.read_selected` | `https://www.googleapis.com/auth/photoslibrary.readonly.appcreateddata` |

Okyema never requests a broader scope than the capability needs.

## Sync

- `GoogleCalendarConnector` uses the Calendar `syncToken` mechanism — the
  cursor is persisted in `sync_cursors` and the same event is upserted
  idempotently by `(provider, provider_event_id)`.
- Receipt filing uploads to `Business Receipts/{Workspace}/{YYYY}/{YYYY-MM}/`
  via `DriveFilesConnector` (files.write).

## Troubleshooting

- **`Error 400: redirect_uri_mismatch`** — the redirect URI the app sent is not
  registered verbatim on the **connector** OAuth client. The app sends
  `/connectors/google/callback` (see Setup step 4): add that exact URI to the
  client's **Authorized redirect URIs**, and make sure `GOOGLE_CALENDAR_REDIRECT_URI`
  (or `APP_URL` in production) points at the same host. A mismatch between
  `https://john.okyema.work` and the host you are actually browsing is the usual cause.
- **"Google sign-in failed"** — the redirect URI is not registered verbatim,
  or the consent screen is still in *Testing* and the account is not a test
  user. Check `GOOGLE_SIGNIN.md`-style notes in `.env.example`.
- **cURL error 60** — no CA bundle; set `CA_BUNDLE_PATH` (see `CaBundle`).
- **Sync never runs** — no stored access token; connect the account first.
