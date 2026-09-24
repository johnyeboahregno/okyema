<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use App\Enums\ConnectorProvider;
use App\Enums\ConnectorStatus;
use App\Models\ConnectorAccount;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OAuth 2.0 authorization-code flow for the calendar connectors (Google and
 * Microsoft). Builds the provider authorization URL, exchanges the code for
 * tokens and upserts a ConnectorAccount so calendar:sync can pick it up.
 *
 * Self-contained on Http — the same client the sync adapters already use —
 * and testable with Http::fake, with no provider SDK required.
 */
final class ConnectorOAuthService
{
    /**
     * Least-privilege scopes for each provider's calendar.read capability.
     *
     * @return list<string>
     */
    public function scopes(ConnectorProvider $provider): array
    {
        return match ($provider) {
            ConnectorProvider::Google => ['https://www.googleapis.com/auth/calendar.readonly'],
            ConnectorProvider::Microsoft => ['offline_access', 'Calendars.Read'],
            ConnectorProvider::Notion => [],
        };
    }

    public function isConfigured(ConnectorProvider $provider): bool
    {
        $config = $this->config($provider);

        return ! empty($config['client_id']) && ! empty($config['client_secret']);
    }

    public function authorizationUrl(ConnectorProvider $provider, string $state): string
    {
        if (! $this->isConfigured($provider)) {
            throw new RuntimeException($provider->label().' is not configured.');
        }

        $config = $this->config($provider);

        return match ($provider) {
            ConnectorProvider::Google => 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
                'client_id' => $config['client_id'],
                'redirect_uri' => $config['redirect_uri'],
                'response_type' => 'code',
                'scope' => implode(' ', $this->scopes($provider)),
                'access_type' => 'offline',
                'prompt' => 'consent',
                'state' => $state,
            ]),
            ConnectorProvider::Microsoft => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?'.http_build_query([
                'client_id' => $config['client_id'],
                'redirect_uri' => $config['redirect_uri'],
                'response_type' => 'code',
                'scope' => implode(' ', $this->scopes($provider)),
                'response_mode' => 'query',
                'state' => $state,
            ]),
            ConnectorProvider::Notion => 'https://api.notion.com/v1/oauth/authorize?'.http_build_query([
                'client_id' => $config['client_id'],
                'redirect_uri' => $config['redirect_uri'],
                'response_type' => 'code',
                'owner' => 'user',
                'state' => $state,
            ]),
        };
    }

    /**
     * Exchange an authorization code for tokens.
     *
     * Notion authenticates the client with HTTP Basic auth and never expires
     * its access token, so `expires_at` is null for Notion.
     *
     * @return array{access_token: string, refresh_token: ?string, expires_at: ?Carbon}
     */
    public function exchange(ConnectorProvider $provider, string $code): array
    {
        $config = $this->config($provider);

        $response = $this->postTokenExchange($provider, $config, $code);

        if ($response->failed()) {
            throw new RuntimeException($provider->label().' token exchange failed: '.$response->body());
        }

        $accessToken = (string) $response->json('access_token');

        if ($accessToken === '') {
            throw new RuntimeException($provider->label().' returned no access token.');
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => $response->json('refresh_token'),
            'expires_at' => $provider === ConnectorProvider::Notion
                ? null
                : now()->addSeconds((int) $response->json('expires_in')),
        ];
    }

    /**
     * The provider-side account identifier (email) stored on the account.
     */
    public function accountId(ConnectorProvider $provider, string $accessToken): string
    {
        $response = match ($provider) {
            ConnectorProvider::Google => Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v3/userinfo'),
            ConnectorProvider::Microsoft => Http::withToken($accessToken)->get('https://graph.microsoft.com/v1.0/me'),
            ConnectorProvider::Notion => Http::withToken($accessToken)
                ->withHeaders(['Notion-Version' => (string) config('okyema.notion.version', '2022-06-28')])
                ->get('https://api.notion.com/v1/users/me'),
        };

        if ($response->failed()) {
            throw new RuntimeException($provider->label().' account lookup failed: '.$response->body());
        }

        $id = strtolower(trim((string) match ($provider) {
            ConnectorProvider::Google => $response->json('email'),
            ConnectorProvider::Microsoft => $response->json('userPrincipalName') ?? $response->json('mail'),
            ConnectorProvider::Notion => $response->json('bot.workspace_id')
                ?? $response->json('bot.workspace_name')
                ?? $response->json('id'),
        }));

        if ($id === '') {
            throw new RuntimeException($provider->label().' did not share an account identifier.');
        }

        return $id;
    }

    /**
     * Upsert the connected account. A missing refresh token (provider only
     * returns one on first consent) keeps the previously stored one.
     *
     * @param  array{access_token: string, refresh_token: ?string, expires_at: ?Carbon}  $tokens
     */
    public function store(User $user, ConnectorProvider $provider, string $accountId, array $tokens): ConnectorAccount
    {
        $existing = ConnectorAccount::query()
            ->where('user_id', $user->id)
            ->where('provider', $provider->value)
            ->where('external_account_id', $accountId)
            ->first();

        return ConnectorAccount::updateOrCreate(
            ['user_id' => $user->id, 'provider' => $provider->value, 'external_account_id' => $accountId],
            [
                'scopes' => $this->scopes($provider),
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? $existing?->refresh_token,
                'expires_at' => $tokens['expires_at'],
                'status' => ConnectorStatus::Connected->value,
                'revoked_at' => null,
            ],
        );
    }

    private function tokenUrl(ConnectorProvider $provider): string
    {
        return match ($provider) {
            ConnectorProvider::Google => 'https://oauth2.googleapis.com/token',
            ConnectorProvider::Microsoft => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            ConnectorProvider::Notion => 'https://api.notion.com/v1/oauth/token',
        };
    }

    /**
     * POST the authorization-code exchange. Notion authenticates the client
     * with HTTP Basic auth and a Notion-Version header instead of form fields.
     *
     * @param  array{client_id: ?string, client_secret: ?string, redirect_uri: string}  $config
     */
    private function postTokenExchange(ConnectorProvider $provider, array $config, string $code)
    {
        if ($provider === ConnectorProvider::Notion) {
            return Http::withBasicAuth((string) $config['client_id'], (string) $config['client_secret'])
                ->withHeaders(['Notion-Version' => (string) config('okyema.notion.version', '2022-06-28')])
                ->asJson()
                ->post($this->tokenUrl($provider), [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $config['redirect_uri'],
                ]);
        }

        return Http::asForm()->post($this->tokenUrl($provider), array_filter([
            'code' => $code,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $config['redirect_uri'],
            'grant_type' => 'authorization_code',
            'scope' => $provider === ConnectorProvider::Microsoft ? implode(' ', $this->scopes($provider)) : null,
        ]));
    }

    /**
     * @return array{client_id: ?string, client_secret: ?string, redirect_uri: string}
     */
    private function config(ConnectorProvider $provider): array
    {
        return [
            'client_id' => config('services.connectors.'.$provider->value.'.client_id'),
            'client_secret' => config('services.connectors.'.$provider->value.'.client_secret'),
            'redirect_uri' => $this->redirectUri($provider),
        ];
    }

    private function redirectUri(ConnectorProvider $provider): string
    {
        $configured = trim((string) config('services.connectors.'.$provider->value.'.redirect'));

        if ($configured !== '') {
            return $configured;
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '' && app()->environment('production')) {
            return $appUrl.'/connectors/'.$provider->value.'/callback';
        }

        $secure = request()->isSecure() || str_starts_with($appUrl, 'https://');

        return url('/connectors/'.$provider->value.'/callback', [], $secure);
    }
}
