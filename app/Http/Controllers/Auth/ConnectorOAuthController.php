<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\ConnectorProvider;
use App\Http\Controllers\Controller;
use App\Models\ConnectorAccount;
use App\Services\Connectors\CalendarSyncService;
use App\Services\Connectors\ConnectorOAuthService;
use App\Services\Connectors\ConnectorRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/**
 * OAuth "add a connector" flow. Connects a Google or Microsoft calendar and
 * stores the tokens on a ConnectorAccount. Sign-in (AuthController) is a
 * separate client and never touches these accounts.
 */
final class ConnectorOAuthController extends Controller
{
    public function __construct(
        private readonly ConnectorOAuthService $oauth,
        private readonly CalendarSyncService $sync,
        private readonly ConnectorRegistry $registry,
    ) {}

    public function redirect(string $provider): RedirectResponse
    {
        $provider = ConnectorProvider::from($provider);

        if (! $this->oauth->isConfigured($provider)) {
            return $this->done($provider->label().' is not configured.');
        }

        $state = Str::random(40);
        session()->put($this->stateKey($provider), $state);

        return redirect()->away($this->oauth->authorizationUrl($provider, $state));
    }

    public function callback(string $provider): RedirectResponse
    {
        $provider = ConnectorProvider::from($provider);

        $state = (string) request()->query('state', '');
        $expected = (string) session()->pull($this->stateKey($provider), '');

        if ($state === '' || ! hash_equals($expected, $state)) {
            return $this->done('Could not verify the connection request. Please try again.');
        }

        $code = (string) request()->query('code', '');

        if ($code === '') {
            return $this->done('The provider did not return an authorisation code.');
        }

        try {
            $tokens = $this->oauth->exchange($provider, $code);
            $accountId = $this->oauth->accountId($provider, $tokens['access_token']);
        } catch (\Throwable $e) {
            report($e);

            return $this->done('Could not connect the calendar. Please try again.');
        }

        $account = $this->oauth->store(request()->user(), $provider, $accountId, $tokens);

        if ($provider !== ConnectorProvider::Notion) {
            $this->syncNow($account, $provider);
        }

        return $this->done(
            $provider === ConnectorProvider::Notion
                ? 'Notion connected. Your assistant can now search it and propose changes for your approval.'
                : $provider->label().' Calendar connected. It will sync on schedule — run `php artisan calendar:sync` to pull it in now.'
        );
    }

    private function syncNow(ConnectorAccount $account, ConnectorProvider $provider): void
    {
        try {
            $context = $this->sync->contextFor($account);

            if ($context !== null) {
                $this->sync->sync($account, $context, $this->registry->calendar($provider));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function done(string $message): RedirectResponse
    {
        session()->flash('connector_notice', $message);

        return redirect('/?tab=settings');
    }

    private function stateKey(ConnectorProvider $provider): string
    {
        return 'connector.oauth.'.$provider->value.'.state';
    }
}
