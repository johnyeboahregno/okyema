<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CalendarService;
use App\Services\WorkspaceContextService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgendaController extends Controller
{
    public function __construct(
        private readonly CalendarService $calendar,
        private readonly WorkspaceContextService $workspaces,
    ) {}

    public function agenda(Request $request): JsonResponse
    {
        $user = $request->user();
        $context = $this->workspaces->activeContext($user);
        $timezone = $request->query('timezone', $user->profile?->timezone ?: 'UTC');
        $day = CarbonImmutable::parse($request->query('date', now()->toDateString()), $timezone);

        return response()->json([
            'data' => [
                'date' => $day->toDateString(),
                'timezone' => $timezone,
                'events' => $this->calendar->agenda($user, $context, $day, $timezone),
            ],
        ]);
    }

    public function timeline(Request $request): JsonResponse
    {
        $user = $request->user();
        $context = $this->workspaces->activeContext($user);

        $from = CarbonImmutable::parse($request->query('from', now()->startOfMonth()->toDateString()));
        $to = CarbonImmutable::parse($request->query('to', now()->startOfMonth()->addDays(13)->toDateString()));

        return response()->json([
            'data' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'events' => $this->calendar->timeline($user, $context, $from, $to),
            ],
        ]);
    }
}
