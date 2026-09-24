<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ConnectorStatus;
use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\ConnectorAccount;
use App\Services\Connectors\ConnectorRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Connector health, without exposing tokens or provider internals.
 */
class ConnectorController extends Controller
{
    use AuthorizesOwnership;

    public function __construct(
        private readonly ConnectorRegistry $registry,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $accounts = $request->user()->connectorAccounts()
            ->orderBy('provider')
            ->get();

        return response()->json([
            'data' => $accounts->map(fn ($account) => [
                'id' => $account->id,
                'provider' => $account->provider->value,
                'status' => $account->status->value,
                'capabilities' => $this->registry->capabilities($account->provider),
                'last_synced_at' => $account->last_synced_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Disconnect an account: clear credentials and stop syncing, keeping the
     * calendars, events and meetings already brought in.
     */
    public function destroy(Request $request, ConnectorAccount $account): JsonResponse
    {
        $this->authorizeModel($account);

        $account->forceFill([
            'status' => ConnectorStatus::Disconnected->value,
            'access_token' => null,
            'refresh_token' => null,
            'expires_at' => null,
            'revoked_at' => now(),
        ])->save();

        return response()->json(null, 204);
    }
}
