<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PeopleService;
use App\Services\WorkspaceContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeopleController extends Controller
{
    public function __construct(
        private readonly PeopleService $people,
        private readonly WorkspaceContextService $workspaces,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $context = $this->workspaces->activeContext($request->user());

        return response()->json([
            'data' => $this->people->index($request->user(), $context)->map(fn ($person) => [
                'id' => $person->id,
                'name' => $person->name,
                'organisation' => $person->organisation,
                'identities' => $person->providerIdentities->map(fn ($identity) => [
                    'provider' => $identity->provider,
                    'email' => $identity->email,
                ]),
            ]),
        ]);
    }
}
