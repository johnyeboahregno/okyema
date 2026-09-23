# Okyema — Google sign-in (OAuth) runbook

Sign-in with Google uses Laravel Socialite behind two web routes
(`/auth/google/redirect` → `/auth/google/callback`) and reads three env vars:

| Var | Used for |
|-----|----------|
| `GOOGLE_CLIENT_ID` | The OAuth client id sent to Google |
| `GOOGLE_CLIENT_SECRET` | The client secret exchanged for a token |
| `GOOGLE_REDIRECT_URI` | Where Google sends the user back (optional — see below) |

Okyema must have its **own** Google OAuth client — do not reuse Courtly's or
SIKA's, and do not reuse the sign-in client for the Calendar/Gmail/Drive
connectors (those are separate clients, see `docs/connectors/google.md`).

## Setup

1. Google Cloud Console → **APIs & Services** → **Credentials** →
   **Create credentials** → **OAuth client ID** → *Web application*.
2. Add **Authorized redirect URIs**, verbatim:
   - `https://john.okyema.work/auth/google/callback` (production)
   - `http://localhost:8080/auth/google/callback` (local dev)
   - `http://localhost:8000/auth/google/callback` (alternate local port)
3. **OAuth consent screen** → add the app and either publish it or add your
   account as a *test user*. While unpublished, only test users can sign in.
4. Set the values in Okyema's `.env` — local `c:\repos\okyema\.env` and
   `~/okyema/.env` on the VPS:
   ```
   GOOGLE_CLIENT_ID=<id>.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=<secret>
   GOOGLE_REDIRECT_URI=https://john.okyema.work/auth/google/callback
   ```
5. VPS: `php artisan config:clear` (or let the next deploy recreate the
   container) so the new values are picked up.

## How the redirect URI is resolved

`GOOGLE_REDIRECT_URI` always wins when set. Without it
(`App\Support\GoogleRedirectUri`):

- **production** (`APP_ENV=production`) derives it from `APP_URL` as
  `<APP_URL>/auth/google/callback` — never from the host the visitor used.
- **local** follows whatever host you are browsing.

So in production you can either set `GOOGLE_REDIRECT_URI` explicitly, or just
make sure `APP_URL=https://john.okyema.work` and leave it empty.

## No credentials vs. wrong credentials

`AuthController` short-circuits when `GOOGLE_CLIENT_ID` or
`GOOGLE_CLIENT_SECRET` is empty and bounces back to `/login` with
*"Google sign-in is not configured."* A non-empty-but-wrong value passes that
check and reaches Google, which then shows its own error page.

## Troubleshooting

| Error | Cause | Fix |
|-------|-------|-----|
| `Error 401: invalid_client` / *"The OAuth client was not found"* | Google doesn't recognise the `client_id` — deleted, rotated, or Okyema is reusing another app's client | Create a fresh OAuth client for Okyema (step 1–4 above) |
| `Error 400: redirect_uri_mismatch` | The redirect URI sent isn't registered verbatim | Add the exact URI to **Authorized redirect URIs** |
| *"Access blocked"* on the consent screen | App still in *Testing* and the account isn't a test user | Publish the app or add the account as a test user |
| *"Google sign-in failed"* in-app | Callback threw (bad token exchange, cURL, etc.) | Check `storage/logs/laravel.log`; verify secret + network |

## Related

- `app/Http/Controllers/Auth/AuthController.php` — `redirectToGoogle()`,
  `handleGoogleCallback()`, `googleConfigured()`.
- `app/Support/GoogleRedirectUri.php` — redirect-URI derivation.
- `docs/connectors/google.md` — the separate Calendar/Gmail/Drive OAuth clients.
- `docs/deployment/DEPLOYMENT.md` — VPS `.env` setup.
