<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Connectors\ConnectorRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Connector health, without exposing tokens or provider internals.
 */
class ConnectorController extends Controller
{
    public function __construct(
        private readonly ConnectorRegistry $registry,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $accounts = $request->user()->connectorAccounts()
            ->orderBy('provider')
            ->get();

        return response()->json([
            'data' => $accounts->map(function ($account) {
                $connector = $this->registry->calendar($account->provider);

                return [
                    'id' => $account->id,
                    'provider' => $account->provider->value,
                    'status' => $account->status->value,
                    'capabilities' => $connector->capabilities(),
                    'last_synced_at' => $account->last_synced_at?->toIso8601String(),
                ];
            }),
        ]);
    }
}
